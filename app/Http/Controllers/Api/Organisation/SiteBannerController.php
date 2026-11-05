<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\SaveSiteBannerRequest;
use App\Http\Resources\SiteBannerResource;
use App\Services\Organisation\SiteBannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SiteBannerController extends Controller
{
    /**
     * Show the homepage banner for a site (null when not configured yet).
     */
    public function show(Request $request, SiteBannerService $banners): JsonResponse
    {
        try {
            $validated = $request->validate([
                'site_id' => ['required', 'integer', 'min:1'],
            ]);

            $response = $banners->show($request->user(), (int) $validated['site_id']);
            $banner = $response['banner'];

            return $this->success(
                __('messages.site_banner_show'),
                [
                    'banner' => $banner === null
                        ? null
                        : SiteBannerResource::make($banner),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Create or update the homepage banner & CTA for a site.
     */
    public function save(SaveSiteBannerRequest $request, SiteBannerService $banners): JsonResponse
    {
        try {
            $response = $banners->save($request->user(), $request->validated());

            return $this->success(
                __('messages.site_banner_saved'),
                [
                    'banner' => SiteBannerResource::make($response['banner']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
