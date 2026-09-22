<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ServiceException extends HttpException implements ShouldntReport
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        int $status,
        string $message = '',
        public readonly ?string $error = null,
        ?Throwable $previous = null,
        array $headers = [],
    ) {
        parent::__construct($status, $message, $previous, $headers);
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(
            $this->getMessage(),
            $this->error,
            $this->getStatusCode(),
        );
    }
}
