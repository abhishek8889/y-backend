<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\RemoveFromWishlistRequest;
use App\Http\Requests\Public\StoreWishlistRequest;
use App\Http\Resources\WishlistResource;
use App\Services\Public\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WishlistController extends Controller
{
    /**
     * Cursor-paginated wishlist for the authenticated user.
     */
    public function list(Request $request, WishlistService $wishlist): JsonResponse
    {
        try {
            $filters = [
                'organisation_id' => $request->filled('organisation_id')
                    ? $request->integer('organisation_id')
                    : null,
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $wishlist->list($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.wishlist_list'),
                WishlistResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Save an event to the authenticated user's wishlist.
     */
    public function store(
        StoreWishlistRequest $request,
        WishlistService $wishlist,
    ): JsonResponse {
        try {
            $response = $wishlist->store($request->user(), $request->validated());

            return $this->success(
                __('messages.wishlist_saved'),
                WishlistResource::make($response['wishlist']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Remove an event from the authenticated user's wishlist.
     */
    public function remove(
        RemoveFromWishlistRequest $request,
        WishlistService $wishlist,
    ): JsonResponse {
        try {
            $response = $wishlist->remove($request->user(), $request->validated());

            return $this->success(
                __('messages.wishlist_removed'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
