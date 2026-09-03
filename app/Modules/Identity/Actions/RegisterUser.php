<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\SellerAccounts\Models\UserPreference;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    public function handle(string $name, string $email, string $password): User
    {
        $user = DB::transaction(function () use ($name, $email, $password): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            UserPreference::query()->create(['user_id' => $user->id]);

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
