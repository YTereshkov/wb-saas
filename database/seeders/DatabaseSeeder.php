<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Notifications\Actions\UpdateNotificationSettings;
use App\Modules\Notifications\NotificationEvents;
use App\Modules\SellerAccounts\Actions\VerifyDemoSellerAccountConnection;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Enums\SyncRunType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \LogicException('Demo data cannot be seeded in production.');
        }

        $email = config('sellerscope.demo.email');
        $password = config('sellerscope.demo.password');
        $token = config('sellerscope.demo.connection_token');
        if (! is_string($email) || $email === ''
            || ! is_string($password) || $password === ''
            || ! is_string($token) || $token === '') {
            throw new \LogicException('Demo credentials must be configured before seeding.');
        }

        $user = User::query()->updateOrCreate([
            'email' => $email,
        ], [
            'name' => 'Юрий Смирнов',
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $account = app(VerifyDemoSellerAccountConnection::class)->handle($user, $token);
        $account->update(['name' => 'Дом и уют']);

        if ($account->products()->doesntExist()) {
            $run = $account->syncRuns()->create([
                'type' => SyncRunType::Initial,
                'status' => SyncRunStatus::Pending,
                'progress' => 0,
            ]);
            $account->update(['status' => SellerAccountStatus::InitialSync, 'last_sync_started_at' => now()]);
            app(RunInitialSync::class)->handle($run);
        } else {
            $account->update(['status' => SellerAccountStatus::Active]);
        }

        app(UpdateNotificationSettings::class)->handle(
            $user,
            $account,
            collect(NotificationEvents::DEFINITIONS)->map(fn (array $definition, string $event): array => [
                'enabled' => $event !== 'daily_digest',
                'threshold' => $definition['threshold'],
                'frequency' => $event === 'daily_digest' ? 'daily' : null,
            ])->all(),
        );
    }
}
