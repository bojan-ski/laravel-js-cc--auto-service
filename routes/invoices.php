<?php

use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Invoices\InvoicePaymentController;
use App\Http\Controllers\Invoices\InvoiceVoidController;
use Illuminate\Support\Facades\Route;

Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

Route::patch('/invoices/{invoice}/paid', [InvoicePaymentController::class, 'markPaid'])->name('invoices.paid');

Route::get('/invoices/{invoice}/void', [InvoiceVoidController::class, 'create'])->name('invoices.void.create');
Route::patch('/invoices/{invoice}/void', [InvoiceVoidController::class, 'void'])->name('invoices.void');
