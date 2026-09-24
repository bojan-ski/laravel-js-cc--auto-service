<?php

use App\Http\Controllers\Preferences\ThemeController;
use Illuminate\Support\Facades\Route;

Route::patch('/preferences/theme', ThemeController::class)->name('preferences.theme.update');
