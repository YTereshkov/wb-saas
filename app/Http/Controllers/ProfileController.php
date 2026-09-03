<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        [$firstName, $lastName] = array_pad(explode(' ', trim($user->name), 2), 2, '');
        $sessions = [];
        if (config('session.driver') === 'database') {
            $sessions = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get()
                ->map(fn (object $session): array => [
                    'id' => $session->id,
                    'current' => hash_equals($request->session()->getId(), $session->id),
                    'ipAddress' => $session->ip_address,
                    'device' => $this->deviceLabel($session->user_agent),
                    'lastActiveAt' => date(DATE_ATOM, $session->last_activity),
                    'lastActiveLabel' => date('d.m.Y, H:i', $session->last_activity),
                ])->all();
        }

        return Inertia::render('settings/profile', [
            'profile' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
                'passwordChangedLabel' => $user->updated_at?->format('d.m.Y'),
            ],
            'sessions' => $sessions,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $emailChanged = $user->email !== $request->validated('email');
        $user->fill([
            'name' => trim($request->validated('first_name').' '.($request->validated('last_name') ?? '')),
            'email' => $request->validated('email'),
        ]);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'profile-updated');
    }

    private function deviceLabel(?string $userAgent): string
    {
        if ($userAgent === null) {
            return 'Неизвестное устройство';
        }

        $browser = str_contains($userAgent, 'Firefox') ? 'Firefox' : (str_contains($userAgent, 'Chrome') ? 'Chrome' : 'Браузер');
        $system = str_contains($userAgent, 'Windows') ? 'Windows' : (str_contains($userAgent, 'Mac') ? 'macOS' : 'Linux');

        return $browser.' · '.$system;
    }
}
