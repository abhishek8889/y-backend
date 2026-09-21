<?php

use App\Http\Controllers\AuthController;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login.store');

Route::post('/organiser/register', [AuthController::class, 'registerOrganiser'])
    ->name('organiser.register');

Route::post('/organiser/verify-email', [AuthController::class, 'verifyOrganiserEmail'])
    ->name('organiser.verify-email');

Route::middleware('jwt')->get('/user', function (Request $request) {
    return ApiResponse::success(data: UserResource::make($request->user()));
})->name('api.user');

