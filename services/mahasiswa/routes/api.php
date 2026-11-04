<?php

use App\Http\Controllers\MahasiswaController;
use Illuminate\Support\Facades\Route;

Route::get('prodi/{id}/usage', [MahasiswaController::class, 'usage'])->whereNumber('id');
Route::apiResource('mahasiswa', MahasiswaController::class);
