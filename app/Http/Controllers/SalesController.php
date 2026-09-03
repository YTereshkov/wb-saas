<?php

namespace App\Http\Controllers;

use App\Modules\Analytics\Queries\SalesAnalyticsQuery;
use App\Modules\Analytics\Queries\SalesProductTableQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function dynamics(Request $request, SalesAnalyticsQuery $sales): Response
    {
        return Inertia::render('sales/dynamics', ['sales' => $sales->forUser($request->user())]);
    }

    public function ordersSales(Request $request, SalesAnalyticsQuery $sales): Response
    {
        return Inertia::render('sales/orders-sales', ['sales' => $sales->forUser($request->user())]);
    }

    public function buyoutReturns(Request $request, SalesAnalyticsQuery $sales): Response
    {
        return Inertia::render('sales/buyout-returns', ['sales' => $sales->forUser($request->user())]);
    }

    public function products(Request $request, SalesAnalyticsQuery $sales, SalesProductTableQuery $table): Response
    {
        return Inertia::render('sales/products', [
            'sales' => $sales->forUser($request->user()),
            'table' => $table->forUser($request->user(), $request),
        ]);
    }
}
