<?php

use App\Http\Controllers\Jobs\JobController;
use App\Http\Controllers\Jobs\JobItemController;
use App\Http\Controllers\Jobs\JobPartLineController;
use App\Http\Controllers\Jobs\JobTransitionController;
use Illuminate\Support\Facades\Route;

Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/create', [JobController::class, 'create'])->name('jobs.create');
Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');

Route::patch('/jobs/{job}/start', [JobTransitionController::class, 'start'])->name('jobs.start');
Route::patch('/jobs/{job}/pause', [JobTransitionController::class, 'pause'])->name('jobs.pause');
Route::patch('/jobs/{job}/resume', [JobTransitionController::class, 'resume'])->name('jobs.resume');
Route::patch('/jobs/{job}/complete', [JobTransitionController::class, 'complete'])->name('jobs.complete');
Route::patch('/jobs/{job}/cancel', [JobTransitionController::class, 'cancel'])->name('jobs.cancel');
Route::patch('/jobs/{job}/reopen', [JobTransitionController::class, 'reopen'])->name('jobs.reopen');

Route::post('/jobs/{job}/items', [JobItemController::class, 'store'])->name('jobs.items.store');
Route::post('/jobs/{job}/parts', [JobPartLineController::class, 'store'])->name('jobs.parts.store');
Route::put('/job-items/{jobItem}', [JobItemController::class, 'update'])->name('job-items.update');
Route::delete('/job-items/{jobItem}', [JobItemController::class, 'destroy'])->name('job-items.destroy');
