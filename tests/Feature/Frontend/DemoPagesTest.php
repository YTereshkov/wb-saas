<?php

namespace Tests\Feature\Frontend;

use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemoPagesTest extends TestCase
{
    #[DataProvider('demoPages')]
    public function test_demo_pages_render_the_expected_inertia_component(
        string $route,
        string $component,
    ): void {
        $this->get(route($route))
            ->assertOk()
            ->assertSee('/images/brand/sellerscope-favicon-16.png', false)
            ->assertSee('/images/brand/sellerscope-favicon-32.png', false)
            ->assertSee('/images/brand/sellerscope-mark.png', false)
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    public static function demoPages(): array
    {
        return [
            'index' => ['demo.index', 'demo/index'],
            'auth' => ['demo.auth', 'demo/auth'],
            'app shell' => ['demo.app', 'demo/app-shell'],
            'settings' => ['demo.settings', 'demo/settings'],
        ];
    }
}
