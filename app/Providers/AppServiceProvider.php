<?php

namespace App\Providers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockSnapshot;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\ReturnFact;
use App\Modules\Sales\Models\Sale;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Policies\SellerAccountPolicy;
use App\Modules\SellerAccounts\Policies\TenantResourcePolicy;
use App\Modules\Synchronization\Models\ImportBatch;
use App\Modules\Synchronization\Models\RawImportPage;
use App\Modules\Synchronization\Models\SyncResourceState;
use App\Modules\Synchronization\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureQueueAlerts();
    }

    protected function configureAuthorization(): void
    {
        Gate::policy(SellerAccount::class, SellerAccountPolicy::class);

        foreach ([
            Category::class,
            Product::class,
            Warehouse::class,
            Order::class,
            Sale::class,
            ReturnFact::class,
            StockSnapshot::class,
            SyncRun::class,
            SyncResourceState::class,
            ImportBatch::class,
            RawImportPage::class,
        ] as $model) {
            Gate::policy($model, TenantResourcePolicy::class);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($this->emailAndIpKey($request)));

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->ip()));

        RateLimiter::for('magic-link', fn (Request $request): Limit => Limit::perMinute(3)
            ->by($this->emailAndIpKey($request)));

        RateLimiter::for('magic-link-consume', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($this->emailAndIpKey($request)));

        RateLimiter::for('verification', fn (Request $request): Limit => Limit::perMinute(6)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    protected function configureQueueAlerts(): void
    {
        Event::listen(QueueBusy::class, static function (QueueBusy $event): void {
            Log::warning('SellerScope queue backlog threshold exceeded.', [
                'connection' => $event->connectionName,
                'queue' => $event->queue,
                'size' => $event->size,
            ]);
        });
    }

    private function emailAndIpKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')).'|'.$request->ip();
    }
}
