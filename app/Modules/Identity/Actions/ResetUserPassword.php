<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetUserPassword
{
    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     */
    public function handle(array $credentials): string
    {
        return Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                if (config('session.driver') === 'database') {
                    DB::table((string) config('session.table', 'sessions'))
                        ->where('user_id', $user->id)
                        ->delete();
                }

                event(new PasswordReset($user));
            },
        );
    }
}
