<?php

namespace App\Http\Controllers;

use App\Modules\Analytics\Queries\AnalyticsContextQuery;
use App\Modules\Analytics\Queries\ProductTableQuery;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly AnalyticsContextQuery $contextQuery,
        private readonly ProductTableQuery $products,
    ) {}

    public function index(Request $request): Response
    {
        return $this->render($request, 'all');
    }

    public function attention(Request $request): Response
    {
        return $this->render($request, 'attention');
    }

    public function decline(Request $request): Response
    {
        return $this->render($request, 'decline');
    }

    public function lowStock(Request $request): Response
    {
        return $this->render($request, 'low_stock');
    }

    private function render(Request $request, string $view): Response
    {
        ['account' => $account, 'period' => $period] = $this->contextQuery->resolveForUser($request->user());
        $table = $account instanceof SellerAccount
            ? $this->products->get($request->user(), $account, $period, $request, $view)
            : ['view' => $view, 'state' => 'empty'];

        return Inertia::render('products/index', ['table' => $table]);
    }
}
