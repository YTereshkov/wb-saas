<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_verification_email(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => ' Юрий ',
            'email' => ' OWNER@example.com ',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => true,
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'owner@example.com')->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('Юрий', $user->name);
        $this->assertTrue(Hash::check('Password1', $user->password));
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_requires_a_confirmed_strong_password_and_terms(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Юрий',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
            'terms' => false,
        ])->assertSessionHasErrors(['password', 'terms']);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }
}
