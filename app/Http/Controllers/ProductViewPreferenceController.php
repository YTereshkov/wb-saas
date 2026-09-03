<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProductViewRequest;
use App\Modules\Analytics\Queries\AnalyticsContextQuery;
use App\Modules\SellerAccounts\Models\SavedView;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Http\RedirectResponse;

class ProductViewPreferenceController extends Controller
{
    public function update(UpdateProductViewRequest $request, AnalyticsContextQuery $contextQuery): RedirectResponse
    {
        $data = $request->validated();
        ['account' => $account] = $contextQuery->resolveForUser($request->user());
        abort_unless($account instanceof SellerAccount, 404);

        SavedView::query()->updateOrCreate([
            'user_id' => $request->user()->id,
            'seller_account_id' => $account->id,
            'section' => 'products',
            'view_key' => $data['view'],
        ], [
            'filters' => $data['filters'],
            'columns' => $data['columns'],
            'sort_column' => $data['sort_column'],
            'sort_direction' => $data['sort_direction'],
            'page_size' => $data['page_size'],
        ]);

        return redirect()->to($data['return_to'])->with('success', 'Представление сохранено.');
    }
}
