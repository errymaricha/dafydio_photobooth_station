<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('auth/Login'))->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('dashboard/Index'))->name('dashboard');
    Route::get('/sessions', fn () => Inertia::render('sessions/Index'))->name('sessions.index');
    Route::get('/sessions/{id}', fn (string $id) => Inertia::render('sessions/Show', ['sessionId' => $id]))->name('sessions.show');
    Route::get('/templates', fn () => Inertia::render('templates/Index'))->name('templates.index');
    Route::get('/templates/{id}', fn (string $id) => Inertia::render('templates/Show', ['templateId' => $id]))->name('templates.show');
    Route::get('/print-orders', fn () => Inertia::render('printorders/Index'))->name('print-orders.index');
    Route::get('/print-orders/{id}', fn (string $id) => Inertia::render('printorders/Show', ['printOrderId' => $id]))->name('print-orders.show');
    Route::get('/print-queue', fn () => Inertia::render('printqueue/Index'))->name('print-queue.index');
    Route::get('/printers', fn () => Inertia::render('printers/Index'))->name('printers.index');
    Route::get('/printers/{id}', fn (string $id) => Inertia::render('printers/Show', ['printerId' => $id]))->name('printers.show');
    Route::get('/print-logs', fn () => Inertia::render('printlogs/Index'))->name('print-logs.index');
    Route::get('/vouchers', fn () => Inertia::render('vouchers/Index'))->name('vouchers.index');
});

require __DIR__.'/settings.php';
