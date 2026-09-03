<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\PasswordlessLoginToken;
use App\Modules\Identity\Notifications\MagicLoginLinkNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueMagicLoginLink
{
    public function handle(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return;
        }

        $plainToken = Str::random(64);

        DB::transaction(function () use ($user, $plainToken): void {
            PasswordlessLoginToken::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            PasswordlessLoginToken::query()->create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addMinutes(15),
            ]);
        });

        $user->notify(new MagicLoginLinkNotification($plainToken));
    }
}
