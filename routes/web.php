<?php

use App\Http\Controllers\AnalyticsContextController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CabinetConnectionController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\CabinetCredentialController;
use App\Http\Controllers\CabinetSyncController;
use App\Http\Controllers\EntryPointController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDetailsController;
use App\Http\Controllers\ProductViewPreferenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::get('/', EntryPointController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
    Route::post('/login/magic-link', [MagicLinkController::class, 'store'])
        ->middleware('throttle:magic-link')
        ->name('magic-link.store');
    Route::get('/login/magic-link/{token}', [MagicLinkController::class, 'consume'])
        ->middleware('throttle:magic-link-consume')
        ->name('magic-link.consume');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:registration')
        ->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password/{token}', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:verification')
        ->name('verification.send');

    Route::middleware('verified')->group(function () {
        Route::get('/overview', [OverviewController::class, 'index'])->name('overview');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/attention', [ProductController::class, 'attention'])->name('products.attention');
        Route::get('/products/decline', [ProductController::class, 'decline'])->name('products.decline');
        Route::get('/products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
        Route::get('/products/{product}', [ProductDetailsController::class, 'overview'])->name('products.show');
        Route::get('/products/{product}/sales', [ProductDetailsController::class, 'sales'])->name('products.show.sales');
        Route::get('/products/{product}/stocks', [ProductDetailsController::class, 'stocks'])->name('products.show.stocks');
        Route::patch('/preferences/product-view', [ProductViewPreferenceController::class, 'update'])
            ->name('preferences.product-view.update');
        Route::get('/sales', [SalesController::class, 'dynamics'])->name('sales.dynamics');
        Route::get('/sales/orders-sales', [SalesController::class, 'ordersSales'])->name('sales.orders-sales');
        Route::get('/sales/buyout-returns', [SalesController::class, 'buyoutReturns'])->name('sales.buyout-returns');
        Route::get('/sales/products', [SalesController::class, 'products'])->name('sales.products');
        Route::get('/stocks', [StockController::class, 'index'])->name('stocks.index');
        Route::get('/stocks/supply', [StockController::class, 'supply'])->name('stocks.supply');
        Route::get('/stocks/out-of-stock', [StockController::class, 'outOfStock'])->name('stocks.out-of-stock');
        Route::get('/stocks/no-movement', [StockController::class, 'noMovement'])->name('stocks.no-movement');

        Route::get('/settings/cabinets', [CabinetController::class, 'index'])
            ->name('settings.cabinets.index');
        Route::get('/settings/notifications', [NotificationSettingsController::class, 'index'])
            ->name('settings.notifications');
        Route::patch('/settings/notifications', [NotificationSettingsController::class, 'update'])
            ->name('settings.notifications.update');
        Route::get('/settings/profile', [ProfileController::class, 'index'])->name('settings.profile');
        Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
        Route::put('/settings/profile/password', [PasswordController::class, 'update'])->name('settings.profile.password');
        Route::delete('/settings/profile/sessions', [SessionController::class, 'destroyOthers'])->name('settings.profile.sessions');
        Route::get('/settings/cabinets/connect', [CabinetConnectionController::class, 'create'])
            ->name('settings.cabinets.connect');
        Route::post('/settings/cabinets/connect/verify', [CabinetConnectionController::class, 'verify'])
            ->middleware('throttle:verification')
            ->name('settings.cabinets.connect.verify');
        Route::get('/settings/cabinets/{cabinet}/verification', [CabinetConnectionController::class, 'verification'])
            ->middleware('tenant:cabinet')
            ->name('settings.cabinets.verification');
        Route::post('/settings/cabinets/{cabinet}/initial-sync', [CabinetConnectionController::class, 'startSync'])
            ->middleware('tenant:cabinet')
            ->name('settings.cabinets.initial-sync.start');
        Route::get('/settings/cabinets/{cabinet}/initial-sync', [CabinetConnectionController::class, 'loading'])
            ->middleware('tenant:cabinet')
            ->name('settings.cabinets.initial-sync.show');
        Route::get('/settings/cabinets/{cabinet}', [CabinetController::class, 'show'])
            ->middleware('tenant:cabinet')->name('settings.cabinets.show');
        Route::patch('/settings/cabinets/{cabinet}', [CabinetController::class, 'update'])
            ->middleware('tenant:cabinet')->name('settings.cabinets.update');
        Route::post('/settings/cabinets/{cabinet}/sync', [CabinetSyncController::class, 'store'])
            ->middleware('tenant:cabinet')->name('settings.cabinets.sync');
        Route::put('/settings/cabinets/{cabinet}/credentials', [CabinetCredentialController::class, 'update'])
            ->middleware('tenant:cabinet')->name('settings.cabinets.credentials');
        Route::delete('/settings/cabinets/{cabinet}', [CabinetController::class, 'destroy'])
            ->middleware('tenant:cabinet')->name('settings.cabinets.destroy');

        Route::patch('/preferences/analytics-context', [AnalyticsContextController::class, 'update'])
            ->name('preferences.analytics-context.update');
    });
});

Route::inertia('/demo', 'demo/index')->name('demo.index');
Route::inertia('/demo/auth', 'demo/auth')->name('demo.auth');
Route::inertia('/demo/app', 'demo/app-shell')->name('demo.app');
Route::inertia('/demo/settings', 'demo/settings')->name('demo.settings');
