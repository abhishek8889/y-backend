<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Organisation\OrganisationController;
use App\Http\Controllers\Api\Platform\OrganisationController as PlatformOrganisationController;
use App\Http\Controllers\Api\ProfileController;
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

Route::middleware('jwt')->group(function () {
    Route::get('/user', function (Request $request) {
        return ApiResponse::success(data: UserResource::make($request->user()));
    })->name('api.user');

    Route::get('/profile/details', [ProfileController::class, 'details'])
        ->name('profile.details');

    Route::get('/organisation/details', [OrganisationController::class, 'getOrganisationDetail']);

    // ################## Super Admin Platform Routes ##################
    Route::prefix('platform')->middleware('platform')->group(function () {
        Route::prefix('organisation')->group(function () {

            Route::get('/list', [PlatformOrganisationController::class, 'list'])
                ->middleware('platform.permission:organisations.read');
            Route::get('/details/{organisation_id}', [PlatformOrganisationController::class, 'getOrganisationDetailById'])
                ->middleware('platform.permission:organisations.read');

        });
    });
});
