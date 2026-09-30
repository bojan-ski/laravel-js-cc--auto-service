<?php

use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    require __DIR__.'/admin.php';
    require __DIR__.'/appointments.php';
    require __DIR__.'/customers.php';
    require __DIR__.'/estimates.php';
    require __DIR__.'/invoices.php';
    require __DIR__.'/job-templates.php';
    require __DIR__.'/jobs.php';
    require __DIR__.'/parts.php';
    require __DIR__.'/preferences.php';
    require __DIR__.'/vehicles.php';
});

require __DIR__.'/auth.php';
