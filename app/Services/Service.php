<?php

namespace App\Services;

use App\Exceptions\ServiceException;

abstract class Service
{
    /**
     * @param  array<string, mixed>  $errors
     */
    protected function fail(int $status, string $message, array $errors = []): never
    {
        throw new ServiceException($status, $message, $errors);
    }

    protected function notFound(?string $message = null): never
    {
        throw ServiceException::notFound($message);
    }

    protected function forbidden(?string $message = null): never
    {
        throw ServiceException::forbidden($message);
    }

    protected function unauthorized(?string $message = null): never
    {
        throw ServiceException::unauthorized($message);
    }

    protected function conflict(string $message): never
    {
        throw ServiceException::conflict($message);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    protected function unprocessable(string $message, array $errors = []): never
    {
        throw ServiceException::unprocessable($message, $errors);
    }
}
