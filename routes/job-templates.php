<?php

use App\Http\Controllers\JobTemplates\JobTemplateController;
use App\Http\Controllers\JobTemplates\JobTemplateItemController;
use Illuminate\Support\Facades\Route;

Route::get('/job-templates', [JobTemplateController::class, 'index'])->name('job-templates.index');
Route::get('/job-templates/create', [JobTemplateController::class, 'create'])->name('job-templates.create');
Route::post('/job-templates', [JobTemplateController::class, 'store'])->name('job-templates.store');
Route::get('/job-templates/{jobTemplate}/edit', [JobTemplateController::class, 'edit'])->name('job-templates.edit');
Route::put('/job-templates/{jobTemplate}', [JobTemplateController::class, 'update'])->name('job-templates.update');
Route::delete('/job-templates/{jobTemplate}', [JobTemplateController::class, 'destroy'])->name('job-templates.destroy');
Route::patch('/job-templates/{jobTemplate}/restore', [JobTemplateController::class, 'restore'])->withTrashed()->name('job-templates.restore');

Route::post('/job-templates/{jobTemplate}/items', [JobTemplateItemController::class, 'store'])->name('job-templates.items.store');

Route::scopeBindings()->group(function () {
    Route::put('/job-templates/{jobTemplate}/items/{item}', [JobTemplateItemController::class, 'update'])->name('job-templates.items.update');
    Route::delete('/job-templates/{jobTemplate}/items/{item}', [JobTemplateItemController::class, 'destroy'])->name('job-templates.items.destroy');
});
