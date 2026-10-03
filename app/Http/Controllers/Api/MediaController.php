<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\DeleteMediaRequest;
use App\Http\Requests\Media\UploadMediaRequest;
use App\Http\Resources\MediaResource;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Throwable;

class MediaController extends Controller
{
    /**
     * Upload media to the configured storage driver.
     */
    public function upload(UploadMediaRequest $request, MediaService $media): JsonResponse
    {
        try {
            /** @var UploadedFile $file */
            $file = $request->file('file');

            $response = $media->upload(
                $request->user(),
                $file,
                $request->validated('context'),
            );

            return $this->success(
                __('messages.media_uploaded'),
                MediaResource::make($response),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Delete an unused temporary media file.
     */
    public function delete(DeleteMediaRequest $request, MediaService $media): JsonResponse
    {
        try {
            $response = $media->delete(
                $request->user(),
                $request->validated('path'),
            );

            return $this->success(
                __('messages.media_deleted'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
