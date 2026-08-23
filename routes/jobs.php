<?php

use App\Http\Controllers\Jobs\JobController;
use Illuminate\Support\Facades\Route;

Route::get('/jobs/create', [JobController::class, 'create'])->name('jobs.create');
Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
