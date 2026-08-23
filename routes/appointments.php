<?php

use App\Http\Controllers\Appointments\AppointmentController;
use App\Http\Controllers\Appointments\AppointmentOverlapController;
use App\Http\Controllers\Appointments\AppointmentTransitionController;
use Illuminate\Support\Facades\Route;

Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
Route::get('/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
Route::get('/appointments/overlaps', AppointmentOverlapController::class)->name('appointments.overlaps');

Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');

Route::patch('/appointments/{appointment}/no-show', [AppointmentTransitionController::class, 'noShow'])->name('appointments.no-show');
Route::patch('/appointments/{appointment}/cancel', [AppointmentTransitionController::class, 'cancel'])->name('appointments.cancel');
