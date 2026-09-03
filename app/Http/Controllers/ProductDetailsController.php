<?php

namespace App\Http\Controllers;

use App\Modules\Analytics\Queries\AnalyticsContextQuery;
use App\Modules\Analytics\Queries\ProductDetailsQuery;
use App\Modules\Catalog\Models\Product;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductDetailsController extends Controller
{
    public function __construct(
        private readonly AnalyticsContextQuery $contextQuery,
        private readonly ProductDetailsQuery $details,
    ) {}

    public function overview(Request $request, Product $product): Response
    {
        return $this->render($request, $product, 'overview');
    }

    public function sales(Request $request, Product $product): Response
    {
        return $this->render($request, $product, 'sales');
    }

    public function stocks(Request $request, Product $product): Response
    {
        return $this->render($request, $product, 'stocks');
    }

    private function render(Request $request, Product $product, string $view): Response
    {
        ['account' => $account, 'period' => $period] = $this->contextQuery->resolveForUser($request->user());

        abort_unless($account instanceof SellerAccount && $product->seller_account_id === $account->id, 404);

        return Inertia::render("products/show/{$view}", [
            'details' => $this->details->get($account, $period, $product),
        ]);
    }
}
