<?php

declare(strict_types=1);

namespace Modufolio\PanelModule\Http;

use Modufolio\Appkit\Inertia\Inertia;
use Modufolio\Panel\Contracts\PageRendererInterface;
use Modufolio\Panel\Http\Page;

/**
 * A panel page as the appkit kernel finishes it: an {@see Inertia} value,
 * which the kernel hands to the renderer the host's InertiaModule wired —
 * root view, asset version, shared props, partial reloads.
 */
final class InertiaPageRenderer implements PageRendererInterface
{
    public function render(Page $page): Inertia
    {
        return Inertia::render($page->component(), $page->props());
    }
}
