<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::view('/', 'dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
