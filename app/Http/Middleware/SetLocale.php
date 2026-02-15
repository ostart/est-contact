<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Проверяем, есть ли запрос на смену языка
        if ($request->has('locale')) {
            $locale = $request->get('locale');
            
            // Проверяем что локаль поддерживается
            if (in_array($locale, ['ru', 'en'])) {
                Session::put('locale', $locale);
            }
        }

        // Устанавливаем язык из сессии или используем по умолчанию
        $locale = Session::get('locale', config('app.locale'));
        App::setLocale($locale);

        return $next($request);
    }
}
