<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Modules\Identity\Actions\ResetUserPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('auth/reset-password', [
            'email' => $request->string('email')->toString(),
            'token' => $token,
        ]);
    }

    public function store(
        ResetPasswordRequest $request,
        ResetUserPassword $resetUserPassword,
    ): RedirectResponse {
        $request->validated();

        $status = $resetUserPassword->handle([
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'password_confirmation' => $request->string('password_confirmation')->toString(),
            'token' => $request->string('token')->toString(),
        ]);

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => 'Ссылка недействительна или устарела. Запросите новую ссылку для сброса пароля.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Пароль обновлён. Теперь вы можете войти.');
    }
}
