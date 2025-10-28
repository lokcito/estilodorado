<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return response()->json([
        'message' => 'Ruta de login no disponible en este contexto (API REST)'
    ], 404);
})->name('login');
