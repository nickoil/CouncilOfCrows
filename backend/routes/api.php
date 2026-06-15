<?php
use App\Http\Controllers\Api\AskController;
use App\Http\Controllers\Api\CostController;
use App\Http\Controllers\Api\SessionsController;
use Illuminate\Support\Facades\Route;

Route::post('/ask', [AskController::class, 'store']);
Route::get('/sessions', [SessionsController::class, 'index']);
Route::get('/sessions/{session}', [SessionsController::class, 'show']);
Route::get('/subjects', [SessionsController::class, 'subjects']);

Route::prefix('costs')->group(function () {
    Route::get('/monthly', [CostController::class, 'monthly']);
    Route::get('/estimate', [CostController::class, 'estimate']);
});
