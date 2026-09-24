<?php

use App\Http\Controllers\Admin\AppointmentHoursController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserOffboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::get('/users/{user}/offboard', [UserOffboardController::class, 'create'])->name('users.offboard');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserOffboardController::class, 'destroy'])->name('users.destroy');
    Route::patch('/users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('users.restore');

    Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('/brands/create', [BrandController::class, 'create'])->name('brands.create');
    Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
    Route::get('/brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
    Route::put('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
    Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
    Route::patch('/brands/{brand}/restore', [BrandController::class, 'restore'])->withTrashed()->name('brands.restore');

    Route::get('/appointment-hours', [AppointmentHoursController::class, 'edit'])->name('appointment-hours.edit');
    Route::put('/appointment-hours', [AppointmentHoursController::class, 'update'])->name('appointment-hours.update');
});
