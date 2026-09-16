<?php

declare(strict_types=1);

namespace Modufolio\PanelModule\Tests\Unit;

use Modufolio\Panel\Http\Page;
use Modufolio\PanelModule\Http\InertiaPageRenderer;
use PHPUnit\Framework\TestCase;

final class InertiaPageRendererTest extends TestCase
{
    public function testAPageBecomesAnInertiaValueWithTheSameComponentAndProps(): void
    {
        $inertia = (new InertiaPageRenderer())->render(Page::render('Resource/Index', ['rows' => []]));

        self::assertSame('Resource/Index', $inertia->component());
        self::assertSame(['rows' => []], $inertia->props());
    }
}
