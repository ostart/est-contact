<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApprovalController;

// Редирект с главной страницы на форму входа
Route::get('/', function () {
    return redirect('/admin/login');
});

// Страница ожидания подтверждения
Route::middleware(['auth'])->group(function () {
    Route::get('/approval/pending', [ApprovalController::class, 'pending'])->name('approval.pending');
    Route::post('/approval/logout', [ApprovalController::class, 'logout'])->name('approval.logout');
});
