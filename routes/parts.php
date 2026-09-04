<?php

use App\Http\Controllers\Parts\PartController;
use App\Http\Controllers\Parts\PartNumberController;
use App\Http\Controllers\Parts\StockAdjustmentController;
use Illuminate\Support\Facades\Route;

Route::get('/parts', [PartController::class, 'index'])->name('parts.index');
Route::get('/parts/create', [PartController::class, 'create'])->name('parts.create');
Route::post('/parts', [PartController::class, 'store'])->name('parts.store');
Route::get('/parts/{part}', [PartController::class, 'show'])->name('parts.show');
Route::get('/parts/{part}/edit', [PartController::class, 'edit'])->name('parts.edit');
Route::put('/parts/{part}', [PartController::class, 'update'])->name('parts.update');
Route::delete('/parts/{part}', [PartController::class, 'destroy'])->name('parts.destroy');
Route::patch('/parts/{part}/restore', [PartController::class, 'restore'])->name('parts.restore');
Route::get('/parts/{part}/adjust', [StockAdjustmentController::class, 'create'])->name('parts.adjust.create');
Route::post('/parts/{part}/adjust', [StockAdjustmentController::class, 'store'])->name('parts.adjust.store');
Route::post('/parts/{part}/numbers', [PartNumberController::class, 'store'])->name('parts.numbers.store');
Route::delete('/parts/{part}/numbers/{number}', [PartNumberController::class, 'destroy'])->name('parts.numbers.destroy');
