<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\PasswordlessLoginToken;
use Illuminate\Support\Facades\DB;

class ConsumeMagicLoginLink
{
    public function handle(string $plainToken): ?User
    {
        return DB::transaction(function () use ($plainToken): ?User {
            $token = PasswordlessLoginToken::query()
                ->with('user')
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            if (! $token || $token->used_at || $token->expires_at->isPast()) {
                return null;
            }

            $token->update(['used_at' => now()]);

            return $token->user;
        });
    }
}
