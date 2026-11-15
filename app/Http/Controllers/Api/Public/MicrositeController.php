<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\GetAllNewsRequest;
use App\Http\Requests\Public\GetVenueDetailRequest;
use App\Http\Requests\Public\HomeCategoryListRequest;
use App\Http\Requests\Public\MicrositeSiteUniqueIdRequest;
use App\Http\Resources\EventListResource;
use App\Http\Resources\OurTeamMemberResource;
use App\Http\Resources\PublicNewsDetailResource;
use App\Http\Resources\SiteNewsResource;
use App\Http\Resources\SiteSupportContactResource;
use App\Http\Resources\VenueResource;
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

    /**
     * Public team members for a published microsite.
     */
    public function getOurTeamMembers(
        HomeCategoryListRequest $request,
        MicrositeService $microsite,
    ): JsonResponse {
        try {
            $response = $microsite->ourTeamMembers($request->validated());
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.public_our_team_members'),
                OurTeamMemberResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * All published news for a microsite (full list, searchable).
     */
    public function getAllNews(
        GetAllNewsRequest $request,
        MicrositeService $microsite,
    ): JsonResponse {
        try {
            $response = $microsite->allNews($request->validated());
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.public_all_news'),
                SiteNewsResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Public news detail by slug for a published microsite.
     */
    public function getNewsDetail(
        MicrositeSiteUniqueIdRequest $request,
        MicrositeService $microsite,
        string $slug,
    ): JsonResponse {
        try {
            $response = $microsite->newsDetail($slug, $request->validated());

            return $this->success(
                __('messages.public_news_detail'),
                [
                    'news' => PublicNewsDetailResource::make($response['news']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Public team member detail by member_unique_id for a published microsite.
     */
    public function getOurTeamMemberDetail(
        MicrositeSiteUniqueIdRequest $request,
        MicrositeService $microsite,
        string $member_unique_id,
    ): JsonResponse {
        try {
            $response = $microsite->ourTeamMemberDetail($member_unique_id, $request->validated());

            return $this->success(
                __('messages.public_our_team_member_detail'),
                [
                    'member' => OurTeamMemberResource::make($response['member']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Public venue detail by unique_id (marketplace or microsite).
     *
     * Query: site_unique_id optional — omit on marketplace, pass on microsite.
     */
    public function getVenueDetail(
        GetVenueDetailRequest $request,
        MicrositeService $microsite,
        string $unique_id,
    ): JsonResponse {
        try {
            $response = $microsite->venueDetail($unique_id, $request->validated());

            return $this->success(
                __('messages.public_venue_detail'),
                [
                    'venue' => VenueResource::make($response['venue']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
