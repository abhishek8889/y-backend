<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\ManageSiteNewsStatusRequest;
use App\Http\Requests\Organisation\StoreSiteNewsRequest;
use App\Http\Requests\Organisation\UpdateSiteNewsRequest;
use App\Http\Resources\SiteNewsResource;
use App\Services\Organisation\SiteNewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SiteNewsController extends Controller
{
    /**
     * Cursor-paginated news list for the organisation.
     */
    public function list(Request $request, SiteNewsService $news): JsonResponse
    {
        try {
            $filters = [
                'site_id' => $request->filled('site_id') ? $request->integer('site_id') : null,
                'status' => $request->filled('status')
                    ? $request->string('status')->toString()
                    : null,
                'search' => $request->filled('search')
                    ? $request->string('search')->toString()
                    : null,
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $news->list($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.site_news_list'),
                SiteNewsResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Create a news item for the authenticated organiser's organisation.
     */
    public function create(StoreSiteNewsRequest $request, SiteNewsService $news): JsonResponse
    {
        try {
            $response = $news->create($request->user(), $request->validated());

            return $this->success(
                __('messages.site_news_created'),
                [
                    'news' => SiteNewsResource::make($response['news']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update news content fields.
     */
    public function update(
        UpdateSiteNewsRequest $request,
        SiteNewsService $news,
        int $news_id,
    ): JsonResponse {
        try {
            $response = $news->update($request->user(), $news_id, $request->validated());

            return $this->success(
                __('messages.site_news_updated'),
                [
                    'news' => SiteNewsResource::make($response['news']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Publish or draft a news item.
     */
    public function manageStatus(
        ManageSiteNewsStatusRequest $request,
        SiteNewsService $news,
        int $news_id,
    ): JsonResponse {
        try {
            $response = $news->manageStatus($request->user(), $news_id, $request->validated());

            return $this->success(
                __('messages.site_news_status_updated'),
                [
                    'news' => SiteNewsResource::make($response['news']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Delete a news item.
     */
    public function delete(Request $request, SiteNewsService $news, int $news_id): JsonResponse
    {
        try {
            $news->delete($request->user(), $news_id);

            return $this->success(
                __('messages.site_news_deleted'),
                [
                    'deleted' => true,
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
