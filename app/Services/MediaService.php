<?php

namespace App\Services;

use App\Enum\MediaStorageDriverEnum;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MediaService extends Service
{
    /**
     * Upload a file using the configured media driver.
     *
     * @return array{
     *     path: string,
     *     url: string,
     *     mime: string|null,
     *     size: int,
     *     original_name: string,
     *     driver: string
     * }
     */
    public function upload(User $user, UploadedFile $file, ?string $context = null): array
    {
        $driver = MediaStorageDriverEnum::fromConfig();

        return match ($driver) {
            MediaStorageDriverEnum::LOCAL => $this->uploadToLocal($user, $file, $context),
            MediaStorageDriverEnum::CLOUDINARY => $this->uploadToCloudinary($user, $file, $context),
            MediaStorageDriverEnum::AWS => $this->uploadToAws($user, $file, $context),
        };
    }

    /**
     * Delete a temporary media file owned by the caller.
     *
     * @return array{path: string}
     */
    public function delete(User $user, string $path): array
    {
        $driver = MediaStorageDriverEnum::fromConfig();

        return match ($driver) {
            MediaStorageDriverEnum::LOCAL => $this->deleteFromLocal($user, $path),
            MediaStorageDriverEnum::CLOUDINARY => $this->deleteFromCloudinary($user, $path),
            MediaStorageDriverEnum::AWS => $this->deleteFromAws($user, $path),
        };
    }

    /**
     * Ensure a tmp path exists and belongs to the caller.
     */
    public function assertOwnedTmpFile(User $user, string $path): void
    {
        $driver = MediaStorageDriverEnum::fromConfig();

        match ($driver) {
            MediaStorageDriverEnum::LOCAL => $this->assertOwnedLocalTmpFile($user, $path),
            MediaStorageDriverEnum::CLOUDINARY,
            MediaStorageDriverEnum::AWS => $this->fail(
                Response::HTTP_NOT_IMPLEMENTED,
                __('messages.media_driver_not_implemented', [
                    'driver' => $driver->value,
                ]),
            ),
        };
    }

    /**
     * Move an owned tmp file into a permanent directory. Removes the tmp file.
     *
     * @return array{path: string, url: string}
     */
    public function moveOwnedTmpTo(User $user, string $tmpPath, string $destinationDirectory): array
    {
        $driver = MediaStorageDriverEnum::fromConfig();

        return match ($driver) {
            MediaStorageDriverEnum::LOCAL => $this->moveOwnedLocalTmpTo($user, $tmpPath, $destinationDirectory),
            MediaStorageDriverEnum::CLOUDINARY,
            MediaStorageDriverEnum::AWS => $this->fail(
                Response::HTTP_NOT_IMPLEMENTED,
                __('messages.media_driver_not_implemented', [
                    'driver' => $driver->value,
                ]),
            ),
        };
    }

    /**
     * Whether a storage path is still in the temporary upload area.
     */
    public function isTmpPath(string $path): bool
    {
        $path = ltrim($path, '/');
        $base = trim((string) config('media.tmp_directory', 'media/tmp'), '/');

        return str_starts_with($path, $base.'/');
    }

    /**
     * Delete a stored media file (tmp or permanent). Ignores missing files.
     */
    public function deleteStoredFile(string $path): void
    {
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            $this->fail(
                Response::HTTP_FORBIDDEN,
                __('messages.media_path_forbidden'),
            );
        }

        $driver = MediaStorageDriverEnum::fromConfig();

        match ($driver) {
            MediaStorageDriverEnum::LOCAL => Storage::disk((string) config('media.local_disk', 'public'))->delete($path),
            MediaStorageDriverEnum::CLOUDINARY,
            MediaStorageDriverEnum::AWS => $this->fail(
                Response::HTTP_NOT_IMPLEMENTED,
                __('messages.media_driver_not_implemented', [
                    'driver' => $driver->value,
                ]),
            ),
        };
    }

    private function assertOwnedLocalTmpFile(User $user, string $path): void
    {
        $path = ltrim($path, '/');
        $this->assertOwnedTmpPath($user, $path);

        $disk = (string) config('media.local_disk', 'public');

        if (! Storage::disk($disk)->exists($path)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.media_not_found'),
            );
        }
    }

    /**
     * @return array{path: string, url: string}
     */
    private function moveOwnedLocalTmpTo(User $user, string $tmpPath, string $destinationDirectory): array
    {
        $tmpPath = ltrim($tmpPath, '/');
        $this->assertOwnedLocalTmpFile($user, $tmpPath);

        $disk = (string) config('media.local_disk', 'public');
        $destinationDirectory = trim($destinationDirectory, '/');
        $filename = basename($tmpPath);
        $destinationPath = $destinationDirectory.'/'.$filename;

        if (Storage::disk($disk)->exists($destinationPath)) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = Str::lower((string) Str::ulid()).($extension !== '' ? '.'.$extension : '');
            $destinationPath = $destinationDirectory.'/'.$filename;
        }

        $moved = Storage::disk($disk)->move($tmpPath, $destinationPath);

        if (! $moved) {
            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.media_move_failed'),
            );
        }

        return [
            'path' => $destinationPath,
            'url' => Storage::disk($disk)->url($destinationPath),
        ];
    }

    /**
     * @return array{
     *     path: string,
     *     url: string,
     *     mime: string|null,
     *     size: int,
     *     original_name: string,
     *     driver: string
     * }
     */
    private function uploadToLocal(User $user, UploadedFile $file, ?string $context): array
    {
        $disk = (string) config('media.local_disk', 'public');
        $directory = $this->tmpDirectoryFor($user, $context);
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $filename = Str::lower((string) Str::ulid()).'.'.$extension;

        $path = $file->storeAs($directory, $filename, $disk);

        if ($path === false) {
            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.media_upload_failed'),
            );
        }

        return [
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'original_name' => $file->getClientOriginalName(),
            'driver' => MediaStorageDriverEnum::LOCAL->value,
        ];
    }

    /**
     * @return array{path: string}
     */
    private function deleteFromLocal(User $user, string $path): array
    {
        $path = ltrim($path, '/');
        $this->assertOwnedTmpPath($user, $path);

        $disk = (string) config('media.local_disk', 'public');

        if (! Storage::disk($disk)->exists($path)) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.media_not_found'),
            );
        }

        Storage::disk($disk)->delete($path);

        return [
            'path' => $path,
        ];
    }

    /**
     * @return array{
     *     path: string,
     *     url: string,
     *     mime: string|null,
     *     size: int,
     *     original_name: string,
     *     driver: string
     * }
     */
    private function uploadToCloudinary(User $user, UploadedFile $file, ?string $context): array
    {
        $this->fail(
            Response::HTTP_NOT_IMPLEMENTED,
            __('messages.media_driver_not_implemented', [
                'driver' => MediaStorageDriverEnum::CLOUDINARY->value,
            ]),
        );
    }

    /**
     * @return array{path: string}
     */
    private function deleteFromCloudinary(User $user, string $path): array
    {
        $this->fail(
            Response::HTTP_NOT_IMPLEMENTED,
            __('messages.media_driver_not_implemented', [
                'driver' => MediaStorageDriverEnum::CLOUDINARY->value,
            ]),
        );
    }

    /**
     * @return array{
     *     path: string,
     *     url: string,
     *     mime: string|null,
     *     size: int,
     *     original_name: string,
     *     driver: string
     * }
     */
    private function uploadToAws(User $user, UploadedFile $file, ?string $context): array
    {
        $this->fail(
            Response::HTTP_NOT_IMPLEMENTED,
            __('messages.media_driver_not_implemented', [
                'driver' => MediaStorageDriverEnum::AWS->value,
            ]),
        );
    }

    /**
     * @return array{path: string}
     */
    private function deleteFromAws(User $user, string $path): array
    {
        $this->fail(
            Response::HTTP_NOT_IMPLEMENTED,
            __('messages.media_driver_not_implemented', [
                'driver' => MediaStorageDriverEnum::AWS->value,
            ]),
        );
    }

    private function tmpDirectoryFor(User $user, ?string $context): string
    {
        $base = trim((string) config('media.tmp_directory', 'media/tmp'), '/');
        $ownerSegment = $this->ownerSegment($user);
        $contextSegment = filled($context)
            ? Str::slug($context)
            : 'general';

        return $base.'/'.$ownerSegment.'/'.$contextSegment;
    }

    private function ownerSegment(User $user): string
    {
        $organisationId = $user->loginContext()['organisation_id'] ?? null;

        if ($organisationId !== null) {
            return 'org_'.$organisationId;
        }

        return 'user_'.$user->id;
    }

    private function assertOwnedTmpPath(User $user, string $path): void
    {
        $base = trim((string) config('media.tmp_directory', 'media/tmp'), '/');
        $expectedPrefix = $base.'/'.$this->ownerSegment($user).'/';

        if (! str_starts_with($path, $expectedPrefix)) {
            $this->fail(
                Response::HTTP_FORBIDDEN,
                __('messages.media_path_forbidden'),
            );
        }

        if (str_contains($path, '..')) {
            $this->fail(
                Response::HTTP_FORBIDDEN,
                __('messages.media_path_forbidden'),
            );
        }
    }
}
