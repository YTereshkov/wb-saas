<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationSettingsRequest;
use App\Modules\Analytics\Queries\AnalyticsContextQuery;
use App\Modules\Notifications\Actions\UpdateNotificationSettings;
use App\Modules\Notifications\Queries\NotificationSettingsQuery;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function index(
        Request $request,
        AnalyticsContextQuery $context,
        NotificationSettingsQuery $query,
    ): Response|RedirectResponse {
        $account = $context->resolveForUser($request->user())['account'];
        if (! $account instanceof SellerAccount) {
            return redirect()->route('settings.cabinets.connect');
        }
        $this->authorize('view', $account);

        return Inertia::render('settings/notifications', [
            'settings' => $query->forAccount($request->user(), $account),
        ]);
    }

    public function update(
        UpdateNotificationSettingsRequest $request,
        UpdateNotificationSettings $action,
    ): RedirectResponse {
        $account = $request->user()->sellerAccounts()->findOrFail($request->integer('seller_account_id'));
        $this->authorize('update', $account);
        $action->handle(
            $request->user(),
            $account,
            $request->validated('events'),
            $request->boolean('email_enabled'),
        );

        return back()->with('status', 'notifications-updated');
    }
}
