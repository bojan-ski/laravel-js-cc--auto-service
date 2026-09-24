<?php

use App\Http\Controllers\Preferences\LocaleController;
use App\Http\Controllers\Preferences\ThemeController;
use Illuminate\Support\Facades\Route;

Route::patch('/preferences/theme', ThemeController::class)->name('preferences.theme.update');
Route::patch('/preferences/locale', LocaleController::class)->name('preferences.locale.update');
