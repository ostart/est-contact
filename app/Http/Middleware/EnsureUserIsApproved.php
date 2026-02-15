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
        if ($request->is('approval/*') || $request->is('admin/logout')) {
            return $next($request);
        }

        if (auth()->check() && !auth()->user()->is_approved) {
            // Если пользователь авторизован, но не подтвержден - перенаправляем на страницу ожидания
            return redirect()->route('approval.pending');
        }

        return $next($request);
    }
}
