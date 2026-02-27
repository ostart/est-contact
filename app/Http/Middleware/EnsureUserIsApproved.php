<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApproved
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Пропускаем страницы, которые не требуют подтверждения
        if ($request->is('approval/*') || $request->is('admin/logout') || $request->is('email/*')) {
            return $next($request);
        }

        $user = auth()->user();

        if (auth()->check() && $user) {
            // Проверяем подтверждение email (если email указан)
            if (filled($user->email) && !$user->hasVerifiedEmail()) {
                return redirect()->route('email.verification.notice');
            }

            // Проверяем подтверждение администратором
            if (!$user->is_approved) {
                return redirect()->route('approval.pending');
            }
        }

        return $next($request);
    }
}
