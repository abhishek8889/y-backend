<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\StoreSiteSupportContactRequest;
use App\Http\Requests\Organisation\UpdateSiteSupportContactRequest;
use App\Http\Resources\SiteSupportContactResource;
use App\Services\Organisation\SiteSupportContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SiteSupportContactController extends Controller
{
    public function list(Request $request, SiteSupportContactService $contacts): JsonResponse
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

            $response = $contacts->list($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.site_support_contact_list'),
                SiteSupportContactResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    public function create(
        StoreSiteSupportContactRequest $request,
        SiteSupportContactService $contacts,
    ): JsonResponse {
        try {
            $response = $contacts->create($request->user(), $request->validated());

            return $this->success(
                __('messages.site_support_contact_created'),
                [
                    'contact' => SiteSupportContactResource::make($response['contact']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    public function update(
        UpdateSiteSupportContactRequest $request,
        SiteSupportContactService $contacts,
        int $contact_id,
    ): JsonResponse {
        try {
            $response = $contacts->update($request->user(), $contact_id, $request->validated());

            return $this->success(
                __('messages.site_support_contact_updated'),
                [
                    'contact' => SiteSupportContactResource::make($response['contact']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    public function delete(
        Request $request,
        SiteSupportContactService $contacts,
        int $contact_id,
    ): JsonResponse {
        try {
            $contacts->delete($request->user(), $contact_id);

            return $this->success(
                __('messages.site_support_contact_deleted'),
                [
                    'deleted' => true,
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
