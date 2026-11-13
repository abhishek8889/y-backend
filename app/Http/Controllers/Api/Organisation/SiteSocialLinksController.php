<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\SaveSiteSocialLinksRequest;
use App\Http\Resources\SiteListResource;
use App\Services\Organisation\SiteSocialLinksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SiteSocialLinksController extends Controller
{
    /**
     * Show social links for a site.
     */
    public function show(Request $request, SiteSocialLinksService $socialLinks): JsonResponse
    {
        try {
            $validated = $request->validate([
                'site_id' => ['required', 'integer', 'min:1'],
            ]);

            $response = $socialLinks->show($request->user(), (int) $validated['site_id']);

            return $this->success(
                __('messages.site_social_links_show'),
                [
                    'site' => SiteListResource::make($response['site']->loadMissing(['domain', 'template'])),
                    'social_links' => $response['social_links'],
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * First-time save of social links for a site.
     */
    public function save(
        SaveSiteSocialLinksRequest $request,
        SiteSocialLinksService $socialLinks,
    ): JsonResponse {
        try {
            $response = $socialLinks->save($request->user(), $request->validated());

            return $this->success(
                __('messages.site_social_links_saved'),
                [
                    'site' => SiteListResource::make($response['site']->loadMissing(['domain', 'template'])),
                    'social_links' => $response['social_links'],
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update / replace social links for a site.
     */
    public function update(
        SaveSiteSocialLinksRequest $request,
        SiteSocialLinksService $socialLinks,
    ): JsonResponse {
        try {
            $response = $socialLinks->update($request->user(), $request->validated());

            return $this->success(
                __('messages.site_social_links_updated'),
                [
                    'site' => SiteListResource::make($response['site']->loadMissing(['domain', 'template'])),
                    'social_links' => $response['social_links'],
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
