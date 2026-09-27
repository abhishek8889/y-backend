<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read string $path
 * @property-read string $url
 * @property-read string|null $mime
 * @property-read int $size
 * @property-read string $original_name
 * @property-read string $driver
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{
         *     path: string,
         *     url: string,
         *     mime: string|null,
         *     size: int,
         *     original_name: string,
         *     driver: string
         * } $media
         */
        $media = $this->resource;

        return [
            'path' => $media['path'],
            'url' => $media['url'],
            'mime' => $media['mime'],
            'size' => $media['size'],
            'original_name' => $media['original_name'],
            'driver' => $media['driver'],
        ];
    }
}
