<?php

declare(strict_types=1);

namespace Modufolio\PanelModule;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Core\AppInterface;
use Modufolio\Appkit\Core\Kernel;
use Modufolio\Appkit\DependencyInjection\ServiceConfigurator;
use Modufolio\Appkit\Module\AbstractModule;
use Modufolio\Panel\Contracts\CurrentUserInterface;
use Modufolio\Panel\Contracts\ExportAdapterProviderInterface;
use Modufolio\Panel\Contracts\PageRendererInterface;
use Modufolio\Panel\Contracts\PermissionReportProviderInterface;
use Modufolio\Panel\Export\NoExportAdapters;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Http\PanelController;
use Modufolio\Panel\Http\ResourceController;
use Modufolio\Panel\Inspection\NoPermissionReport;
use Modufolio\Panel\Inspection\PermissionInspector;
use Modufolio\Panel\Realtime\ChangePublisher;
use Modufolio\Panel\Realtime\NullChangePublisher;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Search\GlobalSearch;
use Modufolio\PanelModule\Http\InertiaPageRenderer;
use Modufolio\PanelModule\Security\TokenCurrentUser;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The panel as an appkit module: list it in config/modules.php and the
 * package's controllers are wired.
 *
 * ```php
 * // config/modules.php
 * return [
 *     \Modufolio\PanelModule\PanelModule::class => ['media_entity' => \App\Entity\Media::class],
 * ];
 * ```
 *
 * The panel itself names no framework. It asks its host for four things,
 * and this module is where appkit answers them:
 *
 *   - {@see CurrentUserInterface}: the signed-in user, read off the kernel's
 *     token storage ({@see TokenCurrentUser}). The host's user class adds
 *     {@see \Modufolio\Panel\Contracts\UserInterface}, which its
 *     `getRoles()` already satisfies.
 *   - {@see PageRendererInterface}: a panel page becomes an appkit Inertia
 *     value ({@see InertiaPageRenderer}), which the kernel finishes through
 *     the host's InertiaModule — root view, shared props, partial reloads.
 *   - the two optional providers, answered with "nothing" until the host
 *     declares its own.
 *
 * Its controller map names every constructor dependency of
 * {@see ResourceController} and {@see PanelController}, so the kernel builds
 * them from the container like any wired controller. Its services are the
 * defaults a host would otherwise declare: a {@see FormResolver} naming the
 * configured media entity, a {@see GlobalSearch} over the mounted resources,
 * a no-formats export provider. Module definitions sit underneath the
 * application's config/services.php, so a host overrides any of them by
 * declaring the same id.
 *
 * Configuration keys:
 *   - `media_entity`: the entity class of the media library, so a to-one
 *     pointing at it is guessed as an image field (null: no media library).
 */
final class PanelModule extends AbstractModule
{
    protected function defaultConfig(): array
    {
        return ['media_entity' => null];
    }

    public function controllers(): array
    {
        return [
            ResourceController::class => [
                'entityManager' => EntityManagerInterface::class,
                'urlGenerator' => UrlGeneratorInterface::class,
                'validator' => ValidatorInterface::class,
                'users' => CurrentUserInterface::class,
                'flashBag' => FlashBagInterface::class,
                'clock' => ClockInterface::class,
                'forms' => FormResolver::class,
                'exports' => ExportAdapterProviderInterface::class,
                'realtime' => ChangePublisher::class,
                'pages' => PageRendererInterface::class,
            ],
            PanelController::class => [
                'users' => CurrentUserInterface::class,
                'search' => GlobalSearch::class,
                'permissions' => PermissionReportProviderInterface::class,
                'pages' => PageRendererInterface::class,
            ],
        ];
    }

    /**
     * The registered resource for an entity class, read off the mounted
     * routes the way the search and the inspector read them. Resources are
     * built by the container on first ask and kept, so a form that guesses
     * five relations resolves each target once.
     *
     * @return \Closure(class-string): ?PanelResource
     */
    private static function resourceLookup(AppInterface $app): \Closure
    {
        /** @var array<class-string, PanelResource|null> $byEntity */
        $byEntity = [];
        /** @var list<PanelResource>|null $resources */
        $resources = null;

        return static function (string $entityClass) use ($app, &$byEntity, &$resources): ?PanelResource {
            if (array_key_exists($entityClass, $byEntity)) {
                return $byEntity[$entityClass];
            }

            if ($resources === null) {
                $resources = [];

                if ($app instanceof Kernel) {
                    foreach (PermissionInspector::resourceClassesIn($app->router()->getRouteCollection()) as $class) {
                        $resources[] = $app->get($class, $class);
                    }
                }
            }

            foreach ($resources as $resource) {
                if ($resource->entityClass() === $entityClass) {
                    return $byEntity[$entityClass] = $resource;
                }
            }

            return $byEntity[$entityClass] = null;
        };
    }

    protected function loadServices(ServiceConfigurator $services, array $config): void
    {
        // The panel's host contracts, answered by the kernel. A host that
        // keeps its user elsewhere, or renders pages another way, declares
        // either id itself; application definitions win over these.
        $services
            ->set(CurrentUserInterface::class, static fn (AppInterface $app): CurrentUserInterface => new TokenCurrentUser($app->tokenStorage()))
            ->set(PageRendererInterface::class, static fn (): PageRendererInterface => new InertiaPageRenderer());

        // Realtime is opt-in: the controller announces every write, and with
        // nothing listening that costs one method call. A host that wants live
        // panels declares its own ChangePublisher, which — like every module
        // default — wins over this one.
        $services->set(ChangePublisher::class, static fn (): ChangePublisher => new NullChangePublisher());

        // The search across resources reads the resources off the routes, as
        // the permission inspector does, so it knows exactly what is mounted.
        $services->set(GlobalSearch::class, static function (AppInterface $app): GlobalSearch {
            // The route collection is the kernel's; the interface exposes the
            // URL generator only, so the concrete kernel is asked for it.
            if (!$app instanceof Kernel) {
                throw new \LogicException(sprintf('The panel\'s search needs the kernel\'s router; %s is not a %s.', get_debug_type($app), Kernel::class));
            }

            return new GlobalSearch(
                $app->entityManager(),
                $app->urlGenerator(),
                $app->get(ClockInterface::class),
                // The second argument is the container's own type check, so
                // a resource registered under someone else's id fails by name
                // here rather than somewhere down the request.
                static fn (string $class): PanelResource => $app->get($class, $class),
                static fn (): array => PermissionInspector::resourceClassesIn($app->router()->getRouteCollection()),
            );
        });

        $declared = $config['media_entity'] ?? null;

        if ($declared !== null && (!is_string($declared) || !class_exists($declared))) {
            throw new \LogicException(sprintf(
                'The panel module\'s "media_entity" must be an existing entity class or null, %s given.',
                is_string($declared) ? '"'.$declared.'"' : get_debug_type($declared),
            ));
        }

        $mediaEntity = $declared;

        $services
            ->set(FormResolver::class, fn (AppInterface $app) => new FormResolver($app->entityManager(), $mediaEntity, self::resourceLookup($app)))
            ->set(ExportAdapterProviderInterface::class, fn () => new NoExportAdapters())
            // No report until a host wires one: only the application knows its
            // roles and what a user carrying one looks like.
            ->set(PermissionReportProviderInterface::class, fn () => new NoPermissionReport());
    }
}
