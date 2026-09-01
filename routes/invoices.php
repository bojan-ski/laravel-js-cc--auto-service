<?php

use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Invoices\InvoicePaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

Route::patch('/invoices/{invoice}/paid', [InvoicePaymentController::class, 'markPaid'])->name('invoices.paid');
