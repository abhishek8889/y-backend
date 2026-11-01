<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\StoreSiteRequest;
use App\Http\Requests\Organisation\UpdateSiteDomainRequest;
use App\Http\Requests\Organisation\UpdateSiteRequest;
use App\Http\Resources\SiteListResource;
use App\Services\Organisation\SiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SiteController extends Controller
{
    /**
     * List sites for the authenticated organiser's organisation.
     */
    public function list(Request $request, SiteService $site): JsonResponse
    {
        try {
            $response = $site->list($request->user());

            return $this->success(
                __('messages.site_list'),
                SiteListResource::collection($response['sites']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Create a draft site without connecting the domain to Vercel.
     */
    public function create(StoreSiteRequest $request, SiteService $site): JsonResponse
    {
        try {
            $response = $site->create($request->user(), $request->validated());

            return $this->success(
                __('messages.site_created'),
                [
                    'site' => SiteListResource::make($response['site']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update site fields / assets. Publishing (status=active) connects the domain to Vercel.
     */
    public function update(UpdateSiteRequest $request, SiteService $site, int $site_id): JsonResponse
    {
        try {
            $response = $site->update($request->user(), $site_id, $request->validated());

            $payload = [
                'site' => SiteListResource::make($response['site']),
            ];

            if ($response['verified'] !== null) {
                $payload['dns_records'] = $response['dns_records'];
                $payload['verified'] = $response['verified'];
            }

            return $this->success(
                __('messages.site_updated'),
                $payload,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Replace the site domain on Vercel and return DNS records for the provider.
     */
    public function updateDomain(
        UpdateSiteDomainRequest $request,
        SiteService $site,
        int $site_id,
    ): JsonResponse {
        try {
            $response = $site->updateDomain(
                $request->user(),
                $site_id,
                (string) $request->validated('domain'),
            );

            $payload = [
                'site' => SiteListResource::make($response['site']),
            ];

            $connectedToVercel = $response['verified'] !== null;

            if ($connectedToVercel) {
                $payload['dns_records'] = $response['dns_records'];
                $payload['verified'] = $response['verified'];
            }

            return $this->success(
                $connectedToVercel
                    ? __('messages.site_domain_updated_on_vercel')
                    : __('messages.site_domain_updated'),
                $payload,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Check DNS/domain status for a site and refresh stored domain status.
     */
    public function checkDomainStatus(Request $request, SiteService $site, int $site_id): JsonResponse
    {
        try {
            $response = $site->checkDomainStatus($request->user(), $site_id);

            return $this->success(
                __('messages.site_domain_status'),
                [
                    'site' => SiteListResource::make($response['site']),
                    'status' => $response['status'],
                    'verified' => $response['verified'],
                    'misconfigured' => $response['misconfigured'],
                    'configured_by' => $response['configured_by'],
                    'dns_records' => $response['dns_records'],
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Refresh domain connectivity from Vercel and update DB status.
     */
    public function refreshDomain(Request $request, SiteService $site, int $site_id): JsonResponse
    {
        try {
            $response = $site->checkDomainStatus($request->user(), $site_id);

            return $this->success(
                __('messages.site_domain_refreshed'),
                [
                    'site' => SiteListResource::make($response['site']),
                    'status' => $response['status'],
                    'verified' => $response['verified'],
                    'misconfigured' => $response['misconfigured'],
                    'configured_by' => $response['configured_by'],
                    'dns_records' => $response['dns_records'],
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
