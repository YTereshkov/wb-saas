<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectionPlaceholderController extends Controller
{
    public function __invoke(Request $request, string $section): Response
    {
        $sections = [
            'products' => 'Товары',
            'sales' => 'Продажи',
            'stocks' => 'Остатки',
        ];

        abort_unless(isset($sections[$section]), 404);

        return Inertia::render('section-placeholder', [
            'section' => $section,
            'title' => $sections[$section],
        ]);
    }
}
