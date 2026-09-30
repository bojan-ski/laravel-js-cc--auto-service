<?php

use App\Http\Controllers\Jobs\AttachmentDownloadController;
use App\Http\Controllers\Jobs\CheckInPhotoController;
use App\Http\Controllers\Jobs\CheckInSignatureController;
use App\Http\Controllers\Jobs\JobAttachmentController;
use App\Http\Controllers\Jobs\JobCheckInController;
use App\Http\Controllers\Jobs\JobController;
use App\Http\Controllers\Jobs\JobEstimateController;
use App\Http\Controllers\Jobs\JobInvoiceController;
use App\Http\Controllers\Jobs\JobItemController;
use App\Http\Controllers\Jobs\JobItemDiscountController;
use App\Http\Controllers\Jobs\JobPartLineController;
use App\Http\Controllers\Jobs\JobTemplateApplicationController;
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

Route::get('/jobs/{job}/invoice/create', [JobInvoiceController::class, 'create'])->name('jobs.invoice.create');
Route::post('/jobs/{job}/invoice', [JobInvoiceController::class, 'store'])->name('jobs.invoice.store');

Route::post('/jobs/{job}/estimates', [JobEstimateController::class, 'store'])->name('jobs.estimates.store');

Route::post('/jobs/{job}/items', [JobItemController::class, 'store'])->name('jobs.items.store');
Route::post('/jobs/{job}/parts', [JobPartLineController::class, 'store'])->name('jobs.parts.store');
Route::post('/jobs/{job}/templates', [JobTemplateApplicationController::class, 'store'])->name('jobs.templates.apply');
Route::put('/job-items/{jobItem}', [JobItemController::class, 'update'])->name('job-items.update');
Route::delete('/job-items/{jobItem}', [JobItemController::class, 'destroy'])->name('job-items.destroy');
Route::patch('/job-items/{jobItem}/discount', [JobItemDiscountController::class, 'update'])->name('job-items.discount.update');

Route::post('/jobs/{job}/attachments', [JobAttachmentController::class, 'store'])->name('jobs.attachments.store');
Route::get('/attachments/{attachment}/{variant?}', [AttachmentDownloadController::class, 'show'])
    ->whereIn('variant', ['thumb', 'full', 'original'])
    ->name('attachments.show');
Route::delete('/attachments/{attachment}', [JobAttachmentController::class, 'destroy'])->name('attachments.destroy');

Route::get('/jobs/{job}/check-in', [JobCheckInController::class, 'show'])->name('jobs.check-in.show');
Route::put('/jobs/{job}/check-in', [JobCheckInController::class, 'update'])->name('jobs.check-in.update');
Route::get('/jobs/{job}/check-in/print', [JobCheckInController::class, 'print'])->name('jobs.check-in.print');
Route::post('/jobs/{job}/check-in/photos', [CheckInPhotoController::class, 'store'])->name('jobs.check-in.photos.store');
Route::patch('/jobs/{job}/check-in/sign', [CheckInSignatureController::class, 'store'])->name('jobs.check-in.sign');
Route::get('/jobs/{job}/check-in/signature', [CheckInSignatureController::class, 'show'])->name('jobs.check-in.signature');
