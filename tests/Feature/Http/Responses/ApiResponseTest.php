<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

test('success responses include success true data and status code', function () {
    Route::get('/__api-response', fn () => ApiResponse::success('Created.', ['id' => 1], 201));

    $this->getJson('/__api-response')
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Created.',
            'data' => ['id' => 1],
        ]);
});

test('error responses include success false error and status code', function () {
    Route::get('/__api-response', fn () => ApiResponse::error('Not allowed.', 403));

    $this->getJson('/__api-response')
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'error' => 'Not allowed.',
        ]);
});
