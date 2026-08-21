<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::view('/', 'pages.dashboard')->name('dashboard');

    require __DIR__.'/appointments.php';
    require __DIR__.'/customers.php';
    require __DIR__.'/vehicles.php';
});

require __DIR__.'/auth.php';
