<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCabinetRequest;
use App\Modules\SellerAccounts\Actions\DeleteSellerAccount;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Queries\CabinetDetailsQuery;
use App\Modules\SellerAccounts\Queries\CabinetListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CabinetController extends Controller
{
    public function index(Request $request, CabinetListQuery $query): Response
    {
        $this->authorize('viewAny', SellerAccount::class);

        return Inertia::render('settings/cabinets/index', [
            'cabinets' => $query->forUser($request->user()),
        ]);
    }

    public function show(SellerAccount $cabinet, CabinetDetailsQuery $query): Response
    {
        $this->authorize('view', $cabinet);

        return Inertia::render('settings/cabinets/show', [
            'cabinet' => $query->forAccount($cabinet),
        ]);
    }

    public function update(UpdateCabinetRequest $request, SellerAccount $cabinet): RedirectResponse
    {
        $cabinet->update($request->validated());

        return back()->with('status', 'cabinet-updated');
    }

    public function destroy(SellerAccount $cabinet, DeleteSellerAccount $action): RedirectResponse
    {
        $this->authorize('delete', $cabinet);
        $action->handle($cabinet);

        return redirect()->route('settings.cabinets.index')->with('status', 'cabinet-deleted');
    }
}
