<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Shared cursor-pagination payload for all list APIs.
 *
 * Response data shape:
 * {
 *   "items": [...],
 *   "pagination": {
 *     "per_page": 15,
 *     "next_cursor": "..."|null,
 *     "prev_cursor": "..."|null,
 *     "has_more": true
 *   }
 * }
 */
final class CursorPaginatedResponse
{
    public const int DEFAULT_PER_PAGE = 15;

    public const int MAX_PER_PAGE = 50;

    /**
     * @return array{items: mixed, pagination: array{per_page: int, next_cursor: string|null, prev_cursor: string|null, has_more: bool}}
     */
    public static function make(mixed $items, CursorPaginator $paginator): array
    {
        return [
            'items' => self::normalizeItems($items),
            'pagination' => [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    public static function resolvePerPage(mixed $perPage): int
    {
        $value = (int) ($perPage ?? self::DEFAULT_PER_PAGE);

        if ($value < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($value, self::MAX_PER_PAGE);
    }

    private static function normalizeItems(mixed $items): mixed
    {
        if ($items instanceof AnonymousResourceCollection || $items instanceof JsonResource) {
            return $items->resolve();
        }

        if ($items instanceof Collection) {
            return $items->values()->all();
        }

        return $items;
    }
}
