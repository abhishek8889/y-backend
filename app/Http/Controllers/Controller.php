<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceException;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

abstract class Controller
{
    protected function success(?string $message = null, mixed $data = null, int $status = 200): JsonResponse
    {
        return ApiResponse::success($message, $data, $status);
    }

    protected function error(string $error, int $status = 400): JsonResponse
    {
        return ApiResponse::error($error, $status);
    }

    protected function failed(Throwable $exception): JsonResponse
    {
        if ($exception instanceof ServiceException) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }

        report($exception);

        return $this->error(__('exceptions.server_error'), Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
