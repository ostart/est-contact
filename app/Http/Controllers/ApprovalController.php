<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    /**
     * Показать страницу ожидания подтверждения администратором
     */
    public function pending()
    {
        return view('approval.pending', [
            'email' => auth()->user()?->email ?? 'вашу почту',
        ]);
    }

    /**
     * Показать страницу с требованием подтвердить email
     */
    public function emailVerificationNotice()
    {
        $user = auth()->user();

        if ($user && $user->hasVerifiedEmail()) {
            return redirect('/admin');
        }

        return view('approval.email-verification', [
            'email' => $user?->email ?? 'вашу почту',
        ]);
    }

    /**
     * Повторно отправить письмо верификации
     */
    public function resendVerification(Request $request)
    {
        $user = auth()->user();

        if ($user && !$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'verification-link-sent');
    }

    /**
     * Показать страницу бана
     */
    public function banned()
    {
        $user = auth()->user();

        if ($user && !$user->is_banned) {
            return redirect('/admin');
        }

        return view('approval.banned', [
            'reason' => $user?->ban_reason,
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
