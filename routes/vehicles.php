<?php

use App\Http\Controllers\Vehicles\CustomerVehicleController;
use App\Http\Controllers\Vehicles\VehicleController;
use App\Http\Controllers\Vehicles\VehicleOwnerController;
use Illuminate\Support\Facades\Route;

Route::get('/customers/{customer}/vehicles/create', [CustomerVehicleController::class, 'create'])->name('customers.vehicles.create');
Route::post('/customers/{customer}/vehicles', [CustomerVehicleController::class, 'store'])->name('customers.vehicles.store');

Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');
Route::get('/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->name('vehicles.edit');
Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
Route::patch('/vehicles/{vehicle}/restore', [VehicleController::class, 'restore'])->withTrashed()->name('vehicles.restore');

Route::get('/vehicles/{vehicle}/owner/edit', [VehicleOwnerController::class, 'edit'])->name('vehicles.owner.edit');
Route::patch('/vehicles/{vehicle}/owner', [VehicleOwnerController::class, 'update'])->name('vehicles.owner.update');
