<?php

namespace App\Http\Controllers;

use App\Modules\Analytics\Queries\OverviewQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function index(Request $request, OverviewQuery $overview): Response
    {
        return Inertia::render('overview/index', [
            'overview' => $overview->forUser($request->user()),
        ]);
    }
}
