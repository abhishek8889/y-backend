<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\HomeCategoryListRequest;
use App\Http\Resources\EventListResource;
use App\Http\Resources\SiteNewsResource;
use App\Http\Resources\SiteSupportContactResource;
use App\Services\Public\MicrositeService;
use Illuminate\Http\JsonResponse;
use Throwable;

class MicrositeController extends Controller
{
    /**
     * Home landing events selected for a published microsite.
     */
    public function getHomeCategoryEvents(
        HomeCategoryListRequest $request,
        MicrositeService $microsite,
    ): JsonResponse {
        try {
            $response = $microsite->homeCategoryEvents($request->validated());
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.public_home_category_events'),
                EventListResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Home landing news selected for a published microsite.
     */
    public function getHomeCategoryNews(
        HomeCategoryListRequest $request,
        MicrositeService $microsite,
    ): JsonResponse {
        try {
            $response = $microsite->homeCategoryNews($request->validated());
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.public_home_category_news'),
                SiteNewsResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Active support contacts for a published microsite.
     */
    public function getSupportContacts(
        HomeCategoryListRequest $request,
        MicrositeService $microsite,
    ): JsonResponse {
        try {
            $response = $microsite->supportContacts($request->validated());
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.public_support_contacts'),
                SiteSupportContactResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
