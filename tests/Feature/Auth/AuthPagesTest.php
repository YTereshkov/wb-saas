<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('guestPages')]
    public function test_guest_auth_pages_render_the_expected_inertia_component(
        string $route,
        string $component,
        array $parameters = [],
    ): void {
        $this->get(route($route, $parameters))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    public function test_entry_point_routes_users_by_identity_state(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));

        $unverifiedUser = User::factory()->unverified()->create();

        $this->actingAs($unverifiedUser)
            ->get(route('home'))
            ->assertRedirect(route('verification.notice'));

        $verifiedUser = User::factory()->create();

        $this->actingAs($verifiedUser)
            ->get(route('home'))
            ->assertRedirect(route('settings.cabinets.connect'));
    }

    public function test_authenticated_user_cannot_open_guest_auth_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public static function guestPages(): array
    {
        return [
            'login' => ['login', 'auth/login'],
            'register' => ['register', 'auth/register'],
            'forgot password' => ['password.request', 'auth/forgot-password'],
            'reset password' => [
                'password.reset',
                'auth/reset-password',
                ['token' => 'test-token', 'email' => 'owner@example.com'],
            ],
        ];
    }
}
