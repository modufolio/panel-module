<?php

declare(strict_types=1);

namespace Modufolio\PanelModule\Routing;

use Modufolio\Appkit\Routing\RouterInterface;
use Modufolio\Panel\Routing\ResourceMenu;
use Symfony\Component\Routing\RouteCollection;

/**
 * {@see ResourceMenu::fromRoutes()} through appkit's route-data cache: the
 * entries are static, so they are extracted once when the routes are built
 * and read back without loading every route from source on each request.
 */
final class CachedResourceMenu
{
    public const CACHE_KEY = 'panel_menu';

    /**
     * @return list<array{route: string, label: string, icon: string|null, group: string|null, order: int, roles: list<string>}>
     */
    public static function fromRouter(RouterInterface $router): array
    {
        /** @var list<array{route: string, label: string, icon: string|null, group: string|null, order: int, roles: list<string>}> $entries */
        $entries = $router->cachedRouteData(
            self::CACHE_KEY,
            static fn (RouteCollection $routes): array => ResourceMenu::fromRoutes($routes),
        );

        return $entries;
    }
}
