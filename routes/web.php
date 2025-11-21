<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [App\Http\Controllers\WelcomeController::class, 'index'])->middleware('guest')->name('welcome');;

// Landing Page Routes
Route::get('/landing', function () {
    return Inertia\Inertia::render('Landing');
})->middleware('guest')->name('landing');

Route::middleware(['2fa','auth','verified','is_active'])->group(function () {
    Route::get('/', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    Route::middleware(['role:Administrator'])->group(function () {
        Route::resource('/users', App\Http\Controllers\System\UserController::class);
        Route::resource('/libraries/suppliers', App\Http\Controllers\Libraries\SupplierController::class);
    });
});

require __DIR__.'/auth.php';
