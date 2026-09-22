<?php

namespace App\Services;

use App\Exceptions\ServiceException;

abstract class Service
{
    protected function fail(int $status, string $message, ?string $error = null): never
    {
        throw new ServiceException($status, $message, $error);
    }
}
