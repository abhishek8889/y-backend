<?php

use App\Exceptions\ServiceException;
use App\Services\Service;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

test('returns json with message and error for a service exception', function () {
    Route::get('/__service-exception', function (): never {
        throw new ServiceException(Response::HTTP_FORBIDDEN, 'Staff cannot assign this role.');
    });

    $this->getJson('/__service-exception')
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'message' => 'Staff cannot assign this role.',
            'error' => 'Staff cannot assign this role.',
        ]);
});

test('returns developer error detail when provided', function () {
    Route::get('/__service-exception', function (): never {
        throw new ServiceException(
            Response::HTTP_BAD_GATEWAY,
            'Unable to send email.',
            'SMTP authentication failed.',
        );
    });

    $this->getJson('/__service-exception')
        ->assertStatus(502)
        ->assertJson([
            'success' => false,
            'message' => 'Unable to send email.',
            'error' => 'SMTP authentication failed.',
        ]);
});

test('returns custom status codes from fail', function () {
    Route::get('/__service-exception', function (): never {
        throw new ServiceException(Response::HTTP_UNPROCESSABLE_ENTITY, 'The role could not be saved.');
    });

    $this->getJson('/__service-exception')
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'message' => 'The role could not be saved.',
            'error' => 'The role could not be saved.',
        ]);
});

test('fail helper throws a service exception', function () {
    $service = new class extends Service
    {
        public function deny(): never
        {
            $this->fail(Response::HTTP_FORBIDDEN, 'Staff cannot assign this role.');
        }
    };

    $service->deny();
})->throws(ServiceException::class, 'Staff cannot assign this role.');
