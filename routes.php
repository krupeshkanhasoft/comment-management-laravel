<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CommentsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tokenverified'])->group(function () {
    Route::apiResource('comments', CommentsController::class);
});