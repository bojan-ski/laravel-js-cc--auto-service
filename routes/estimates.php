<?php

use App\Http\Controllers\Estimates\EstimateController;
use App\Http\Controllers\Estimates\EstimateDecisionController;
use App\Http\Controllers\Estimates\EstimateEmailController;
use Illuminate\Support\Facades\Route;

Route::get('/estimates/{estimate}', [EstimateController::class, 'show'])
    ->whereNumber('estimate')
    ->name('estimates.show');

Route::patch('/estimates/{estimate}/decision', [EstimateDecisionController::class, 'update'])
    ->whereNumber('estimate')
    ->name('estimates.decision.update');

Route::post('/estimates/{estimate}/email', [EstimateEmailController::class, 'store'])
    ->whereNumber('estimate')
    ->name('estimates.email.store');
