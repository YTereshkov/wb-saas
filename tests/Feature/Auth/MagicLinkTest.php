<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identity\Models\PasswordlessLoginToken;
use App\Modules\Identity\Notifications\MagicLoginLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_and_consume_a_single_use_magic_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create([
            'email' => 'owner@example.com',
        ]);
        $plainToken = null;

        $this->from(route('login'))->post(route('magic-link.store'), [
            'email' => ' OWNER@example.com ',
        ])->assertRedirect(route('login'))->assertSessionHas('magic_link_sent', true);

        Notification::assertSentTo(
            $user,
            MagicLoginLinkNotification::class,
            function (MagicLoginLinkNotification $notification) use (&$plainToken): bool {
                $plainToken = $notification->token;

                return true;
            },
        );

        $this->assertNotNull($plainToken);
        $this->assertDatabaseHas('passwordless_login_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'used_at' => null,
        ]);

        $this->get(route('magic-link.consume', $plainToken))
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(
            PasswordlessLoginToken::query()->sole()->used_at,
        );

        $this->post(route('logout'));
        $this->get(route('magic-link.consume', $plainToken))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('magic_link');
        $this->assertGuest();
    }

    public function test_expired_magic_link_is_rejected(): void
    {
        $user = User::factory()->create();
        $plainToken = Str::random(64);

        PasswordlessLoginToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subSecond(),
        ]);

        $this->get(route('magic-link.consume', $plainToken))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('magic_link');
        $this->assertGuest();
    }

    public function test_unknown_email_receives_the_same_safe_response(): void
    {
        Notification::fake();

        $this->from(route('login'))->post(route('magic-link.store'), [
            'email' => 'missing@example.com',
        ])->assertRedirect(route('login'))->assertSessionHas('magic_link_sent', true);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('passwordless_login_tokens', 0);
    }
}
