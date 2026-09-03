<?php

namespace App\Http\Controllers;

use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\StartInitialSync;
use Illuminate\Http\RedirectResponse;

class CabinetSyncController extends Controller
{
    public function store(SellerAccount $cabinet, StartInitialSync $action): RedirectResponse
    {
        $this->authorize('update', $cabinet);
        $action->handle($cabinet);

        return back()->with('status', 'sync-started');
    }
}
