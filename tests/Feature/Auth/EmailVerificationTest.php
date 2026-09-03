<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_can_open_verification_notice(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'owner@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/verify-email')
                ->where('email', 'owner@example.com'));
    }

    public function test_email_can_be_verified_with_a_signed_link(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect(route('home'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_rejects_a_different_authenticated_user(): void
    {
        $target = User::factory()->unverified()->create();
        $otherUser = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $target->id, 'hash' => sha1($target->email)],
        );

        $this->actingAs($otherUser)->get($verificationUrl)->assertForbidden();
        $this->assertNull($target->fresh()->email_verified_at);
    }

    public function test_unverified_user_can_request_another_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}
