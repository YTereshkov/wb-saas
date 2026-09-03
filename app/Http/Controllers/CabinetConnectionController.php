<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConnectSellerAccountRequest;
use App\Modules\SellerAccounts\Actions\VerifySellerAccountConnection;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Models\SellerAccountCredential;
use App\Modules\Synchronization\Actions\StartInitialSync;
use App\Modules\Synchronization\Queries\InitialSyncStatusQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CabinetConnectionController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', SellerAccount::class);

        return Inertia::render('settings/cabinets/connect/token');
    }

    public function verify(
        ConnectSellerAccountRequest $request,
        VerifySellerAccountConnection $action,
    ): RedirectResponse {
        $account = $action->handle($request->user(), $request->validated('token'));

        return redirect()->route('settings.cabinets.verification', $account);
    }

    public function verification(SellerAccount $cabinet): Response
    {
        $this->authorize('view', $cabinet);
        $cabinet->load('credential');
        $credential = $cabinet->getRelation('credential');

        return Inertia::render('settings/cabinets/connect/verification', [
            'cabinet' => [
                'id' => $cabinet->id,
                'name' => $cabinet->name,
                'wbAccountId' => $cabinet->displayWbAccountId(),
                'environment' => $cabinet->environment(),
                'permissions' => $credential instanceof SellerAccountCredential
                    ? ($credential->permissions ?? [])
                    : [],
            ],
        ]);
    }

    public function startSync(SellerAccount $cabinet, StartInitialSync $action): RedirectResponse
    {
        $this->authorize('update', $cabinet);
        $action->handle($cabinet);

        return redirect()->route('settings.cabinets.initial-sync.show', $cabinet);
    }

    public function loading(
        SellerAccount $cabinet,
        InitialSyncStatusQuery $query,
    ): Response {
        $this->authorize('view', $cabinet);

        return Inertia::render('settings/cabinets/connect/loading', [
            'syncStatus' => fn () => $query->forAccount($cabinet->fresh()),
        ]);
    }
}
