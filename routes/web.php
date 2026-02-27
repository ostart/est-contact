<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\VerifyEmailController;

// Редирект с главной страницы на форму входа
Route::get('/', function () {
    return redirect('/admin/login');
});

// Верификация email по ссылке из письма (без обязательного входа — иначе в БД не проставляется email_verified_at)
Route::get('/admin/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('app.email.verify');

// Страницы ожидания подтверждения
Route::middleware(['auth'])->group(function () {
    Route::get('/approval/pending', [ApprovalController::class, 'pending'])->name('approval.pending');
    Route::post('/approval/logout', [ApprovalController::class, 'logout'])->name('approval.logout');

    // Страница верификации email
    Route::get('/email/verify', [ApprovalController::class, 'emailVerificationNotice'])->name('email.verification.notice');
    Route::post('/email/resend', [ApprovalController::class, 'resendVerification'])->name('email.resend');
});
