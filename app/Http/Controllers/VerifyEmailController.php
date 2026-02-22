<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Верификация email по подписанной ссылке без обязательного входа.
 * Маршрут Filament требует auth — при переходе из письма пользователь часто не залогинен,
 * из‑за чего email_verified_at не проставлялся. Этот контроллер проверяет подпись,
 * находит пользователя по id, проверяет hash и сохраняет подтверждение в БД.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (! $user) {
            return redirect()
                ->route('filament.admin.auth.login')
                ->with('error', __('Электронная почта не найдена или ссылка устарела.'));
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()
                ->route('filament.admin.auth.login')
                ->with('error', __('Некорректная ссылка подтверждения.'));
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()
                ->route('filament.admin.auth.login')
                ->with('status', __('Электронная почта уже подтверждена. Вы можете войти.'));
        }

        $user->markEmailAsVerified();

        return redirect()
            ->route('filament.admin.auth.login')
            ->with('status', __('Электронная почта успешно подтверждена. Вы можете войти.'));
    }
}
