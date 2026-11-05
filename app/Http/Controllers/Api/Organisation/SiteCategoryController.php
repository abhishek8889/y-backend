<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\UpdateSiteCategoryRequest;
use App\Http\Resources\SiteCategoryResource;
use App\Services\Organisation\SiteCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SiteCategoryController extends Controller
{
    /**
     * Show feature toggles + selected landing-page categories for a site.
     */
    public function show(Request $request, SiteCategoryService $categories): JsonResponse
    {
        try {
            $validated = $request->validate([
                'site_id' => ['required', 'integer', 'min:1'],
            ]);

            $response = $categories->show($request->user(), (int) $validated['site_id']);

            return $this->success(
                __('messages.site_category_show'),
                [
                    'feature_object' => $response['feature_object'],
                    'categories' => SiteCategoryResource::collection($response['categories']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update feature_object toggles and replace site_categories rows.
     */
    public function update(
        UpdateSiteCategoryRequest $request,
        SiteCategoryService $categories,
    ): JsonResponse {
        try {
            $response = $categories->update($request->user(), $request->validated());

            return $this->success(
                __('messages.site_category_updated'),
                [
                    'feature_object' => $response['feature_object'],
                    'categories' => SiteCategoryResource::collection($response['categories']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
