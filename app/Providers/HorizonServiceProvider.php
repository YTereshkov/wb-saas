<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(
            static fn (Request $request): bool => Gate::check('viewHorizon', [$request->user()]),
        );

        $alertEmail = config('horizon.alert_email');
        if (is_string($alertEmail) && $alertEmail !== '') {
            Horizon::routeMailNotificationsTo($alertEmail);
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user): bool {
            if ($user === null) {
                return false;
            }

            /** @var list<string> $allowedEmails */
            $allowedEmails = config('horizon.allowed_emails', []);

            return in_array(strtolower($user->email), $allowedEmails, true);
        });
    }
}
