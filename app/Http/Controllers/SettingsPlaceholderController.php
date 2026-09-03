<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SettingsPlaceholderController extends Controller
{
    public function __invoke(string $section): Response
    {
        $sections = [
            'notifications' => ['tab' => 'notifications', 'title' => 'Уведомления'],
            'profile' => ['tab' => 'profile', 'title' => 'Профиль'],
        ];

        abort_unless(isset($sections[$section]), 404);

        return Inertia::render('settings/placeholder', $sections[$section]);
    }
}
