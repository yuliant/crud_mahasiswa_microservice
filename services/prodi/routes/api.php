<?php

use App\Http\Controllers\ProdiController;
use Illuminate\Support\Facades\Route;

Route::get('prodi/options', [ProdiController::class, 'options']);
Route::apiResource('prodi', ProdiController::class);
