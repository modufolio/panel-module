<?php

declare(strict_types=1);

namespace Modufolio\PanelModule\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Core\AppInterface;
use Modufolio\Appkit\DependencyInjection\ServiceConfigurator;
use Modufolio\Appkit\Security\Token\TokenStorageInterface;
use Modufolio\Panel\Contracts\CurrentUserInterface;
use Modufolio\Panel\Contracts\ExportAdapterProviderInterface;
use Modufolio\Panel\Contracts\PageRendererInterface;
use Modufolio\Panel\Export\NoExportAdapters;
use Modufolio\Panel\Form\FormResolver;
use Modufolio\Panel\Http\PanelController;
use Modufolio\Panel\Http\ResourceController;
use Modufolio\PanelModule\Http\InertiaPageRenderer;
use Modufolio\PanelModule\PanelModule;
use Modufolio\PanelModule\Security\TokenCurrentUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The module is the whole registration: its controller map covers each
 * controller's constructor, and its services are the panel's host contracts
 * plus the defaults a host may override.
 */
final class PanelModuleTest extends TestCase
{
    public function testItIsNamedPanel(): void
    {
        self::assertSame('panel', (new PanelModule())->name());
    }

    /** @return iterable<string, array{class-string}> */
    public static function controllers(): iterable
    {
        yield 'resource controller' => [ResourceController::class];
        yield 'panel controller' => [PanelController::class];
    }

    /** @param class-string $controller */
    #[DataProvider('controllers')]
    public function testTheControllerMapNamesEveryConstructorParameter(string $controller): void
    {
        $map = (new PanelModule())->controllers()[$controller];

        $parameters = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            (new \ReflectionMethod($controller, '__construct'))->getParameters(),
        );

        self::assertSame($parameters, array_keys($map), 'Named arguments, one per parameter, in signature order.');

        foreach ($map as $parameter => $id) {
            self::assertTrue(interface_exists($id) || class_exists($id), sprintf('"%s" for $%s is a real id.', $id, $parameter));
        }
    }

    public function testItAnswersThePanelsHostContractsFromTheKernel(): void
    {
        $services = new ServiceConfigurator();
        (new PanelModule())->services($services, []);

        $app = $this->createStub(AppInterface::class);
        $app->method('tokenStorage')->willReturn($this->createStub(TokenStorageInterface::class));

        self::assertInstanceOf(TokenCurrentUser::class, $services->definitions[CurrentUserInterface::class]($app));
        self::assertInstanceOf(InertiaPageRenderer::class, $services->definitions[PageRendererInterface::class]($app));
    }

    public function testItsServicesAreTheDefaultsAHostWouldOtherwiseDeclare(): void
    {
        $services = new ServiceConfigurator();
        (new PanelModule())->services($services, ['media_entity' => \stdClass::class]);

        $app = $this->createStub(AppInterface::class);
        $app->method('entityManager')->willReturn($this->createStub(EntityManagerInterface::class));

        self::assertInstanceOf(FormResolver::class, $services->definitions[FormResolver::class]($app));

        $exports = $services->definitions[ExportAdapterProviderInterface::class]($app);
        self::assertInstanceOf(NoExportAdapters::class, $exports);

        $this->expectException(\InvalidArgumentException::class);
        $exports->get('csv');
    }

    public function testAnUnknownConfigKeyIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('media_entity');

        (new PanelModule())->services(new ServiceConfigurator(), ['media' => 'App\Entity\Media']);
    }

    public function testMediaEntityMustBeAnExistingClassOrNull(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"App\\Entity\\Nope"');

        (new PanelModule())->services(new ServiceConfigurator(), ['media_entity' => 'App\\Entity\\Nope']);
    }
}
