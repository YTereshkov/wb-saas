<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_password_and_remember_session(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'Password1',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => ' OWNER@example.com ',
            'password' => 'Password1',
            'remember' => true,
        ]);

        $response->assertRedirect(route('home'));

        $recallerName = Auth::guard()->getRecallerName();
        $recaller = $response->getCookie($recallerName);

        $this->assertNotNull($recaller);
        $this->assertNotNull($user->fresh()->getRememberToken());

        Auth::forgetGuards();
        $this->app['session']->flush();

        $this->withCookie($recallerName, $recaller->getValue())
            ->get(route('home'))
            ->assertRedirect(route('settings.cabinets.connect'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Auth::viaRemember());
    }

    public function test_invalid_credentials_are_rejected_without_revealing_the_field(): void
    {
        User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'Password1',
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'missing@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
