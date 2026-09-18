<?php

use App\Exceptions\ServiceException;
use App\Services\Service;
use Illuminate\Support\Facades\Route;

test('returns json with the custom message when a service throws forbidden', function () {
    Route::get('/__service-exception', function (): never {
        throw ServiceException::forbidden('Staff cannot assign this role.');
    });

    $this->getJson('/__service-exception')
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'error' => 'Staff cannot assign this role.',
        ]);
});

test('returns 422 when a service throws unprocessable', function () {
    Route::get('/__service-exception', function (): never {
        throw ServiceException::unprocessable('The role could not be saved.', [
            'name' => ['The name has already been taken.'],
        ]);
    });

    $this->getJson('/__service-exception')
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'error' => 'The role could not be saved.',
        ]);
});

test('returns not found when a service throws notFound', function () {
    Route::get('/__service-exception', function (): never {
        throw ServiceException::notFound('Organisation not found.');
    });

    $this->getJson('/__service-exception')
        ->assertNotFound()
        ->assertJson([
            'success' => false,
            'error' => 'Organisation not found.',
        ]);
});

test('forbidden helper throws a service exception', function () {
    $service = new class extends Service
    {
        public function deny(): never
        {
            $this->forbidden('Staff cannot assign this role.');
        }
    };

    $service->deny();
})->throws(ServiceException::class, 'Staff cannot assign this role.');
