<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ServiceException extends HttpException implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, string>  $headers
     */
    public function __construct(
        int $status,
        string $message = '',
        public readonly array $errors = [],
        ?Throwable $previous = null,
        array $headers = [],
    ) {
        parent::__construct($status, $message, $previous, $headers);
    }

    public static function notFound(?string $message = null): self
    {
        return new self(Response::HTTP_NOT_FOUND, $message ?? __('exceptions.not_found'));
    }

    public static function forbidden(?string $message = null): self
    {
        return new self(Response::HTTP_FORBIDDEN, $message ?? __('exceptions.forbidden'));
    }

    public static function unauthorized(?string $message = null, ?Throwable $previous = null): self
    {
        return new self(Response::HTTP_UNAUTHORIZED, $message ?? __('exceptions.unauthorized'), previous: $previous);
    }

    public static function conflict(string $message): self
    {
        return new self(Response::HTTP_CONFLICT, $message);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function unprocessable(string $message, array $errors = []): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, $message, $errors);
    }

    public static function serverError(?string $message = null, ?Throwable $previous = null): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, $message ?? __('exceptions.server_error'), previous: $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->getStatusCode());
    }
}
