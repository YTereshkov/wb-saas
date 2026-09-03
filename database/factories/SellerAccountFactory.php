<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SellerAccount> */
class SellerAccountFactory extends Factory
{
    protected $model = SellerAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'wb_account_id' => (string) fake()->unique()->numberBetween(100_000, 999_999),
            'source' => SellerAccountSource::Demo,
            'status' => SellerAccountStatus::Pending,
            'timezone' => 'Europe/Moscow',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => SellerAccountStatus::Active,
        ]);
    }
}
