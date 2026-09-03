<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_request_does_not_reveal_unknown_email(): void
    {
        Notification::fake();

        $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'missing@example.com',
        ])->assertRedirect(route('password.request'))->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_and_existing_sessions_are_terminated(): void
    {
        config(['session.driver' => 'database']);

        Notification::fake();
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'OldPassword1',
        ]);
        $plainToken = null;

        DB::table('sessions')->insert([
            [
                'id' => 'session-one',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test agent',
                'payload' => 'test',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'session-two',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test agent',
                'payload' => 'test',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $this->post(route('password.email'), [
            'email' => 'owner@example.com',
        ])->assertSessionHas('status');

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$plainToken): bool {
                $plainToken = $notification->token;

                return true;
            },
        );

        $this->post(route('password.store', $plainToken), [
            'token' => $plainToken,
            'email' => 'owner@example.com',
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewPassword2', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertGuest();
    }

    public function test_invalid_reset_token_does_not_change_password(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'OldPassword1',
        ]);

        $this->from(route('password.reset', 'invalid-token'))->post(
            route('password.store', 'invalid-token'),
            [
                'token' => 'invalid-token',
                'email' => 'owner@example.com',
                'password' => 'NewPassword2',
                'password_confirmation' => 'NewPassword2',
            ],
        )->assertRedirect(route('password.reset', 'invalid-token'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldPassword1', $user->fresh()->password));
    }
}
