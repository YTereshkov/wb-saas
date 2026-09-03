<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailRequest;
use App\Modules\Identity\Actions\ConsumeMagicLoginLink;
use App\Modules\Identity\Actions\IssueMagicLoginLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MagicLinkController extends Controller
{
    public function store(EmailRequest $request, IssueMagicLoginLink $issueMagicLoginLink): RedirectResponse
    {
        $issueMagicLoginLink->handle($request->string('email')->toString());

        return back()
            ->with('status', 'Если аккаунт существует, ссылка для входа уже отправлена на почту.')
            ->with('magic_link_sent', true);
    }

    public function consume(
        Request $request,
        string $token,
        ConsumeMagicLoginLink $consumeMagicLoginLink,
    ): RedirectResponse {
        $user = $consumeMagicLoginLink->handle($token);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'magic_link' => 'Одноразовая ссылка недействительна или уже использована. Запросите новую.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
