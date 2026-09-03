<?php

namespace App\Http\Controllers;

use App\Modules\Analytics\Queries\StockAnalyticsQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request, StockAnalyticsQuery $stocks): Response
    {
        return $this->render($request, $stocks, 'all');
    }

    public function supply(Request $request, StockAnalyticsQuery $stocks): Response
    {
        return $this->render($request, $stocks, 'supply');
    }

    public function outOfStock(Request $request, StockAnalyticsQuery $stocks): Response
    {
        return $this->render($request, $stocks, 'out_of_stock');
    }

    public function noMovement(Request $request, StockAnalyticsQuery $stocks): Response
    {
        return $this->render($request, $stocks, 'no_movement');
    }

    private function render(Request $request, StockAnalyticsQuery $stocks, string $view): Response
    {
        return Inertia::render('stocks/index', ['stocks' => $stocks->forUser($request->user(), $request, $view)]);
    }
}
