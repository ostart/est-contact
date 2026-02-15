<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    /**
     * Показать страницу ожидания подтверждения
     */
    public function pending()
    {
        return view('approval.pending', [
            'email' => auth()->user()?->email ?? 'вашу почту',
        ]);
    }

    /**
     * Выйти из системы
     */
    public function logout(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/admin/login');
    }
}
