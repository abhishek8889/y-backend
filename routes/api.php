<?php

use App\Http\Controllers\AuthController;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login.store');

Route::middleware('jwt')->get('/user', function (Request $request) {
    return ApiResponse::success(data: UserResource::make($request->user()));
})->name('api.user');
