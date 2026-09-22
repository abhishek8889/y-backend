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

test('error responses include success false message error and status code', function () {
    Route::get('/__api-response', fn () => ApiResponse::error('Not allowed.', null, 403));

    $this->getJson('/__api-response')
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'message' => 'Not allowed.',
            'error' => 'Not allowed.',
        ]);
});

test('error responses can include a separate developer error detail', function () {
    Route::get('/__api-response', fn () => ApiResponse::error('Unable to send email.', 'SMTP failed.', 502));

    $this->getJson('/__api-response')
        ->assertStatus(502)
        ->assertJson([
            'success' => false,
            'message' => 'Unable to send email.',
            'error' => 'SMTP failed.',
        ]);
});
