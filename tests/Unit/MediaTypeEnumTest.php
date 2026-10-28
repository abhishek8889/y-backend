<?php

use App\Enum\MediaTypeEnum;

test('media type detects images from common extensions', function () {
    expect(MediaTypeEnum::fromPath('venues/1/banner.jpg'))->toBe(MediaTypeEnum::IMAGE)
        ->and(MediaTypeEnum::fromPath('venues/1/banner.PNG'))->toBe(MediaTypeEnum::IMAGE)
        ->and(MediaTypeEnum::fromPath('venues/1/gallery.webp'))->toBe(MediaTypeEnum::IMAGE);
});

test('media type detects videos from configured extensions', function () {
    expect(MediaTypeEnum::fromPath('venues/1/banner.mp4'))->toBe(MediaTypeEnum::VIDEO)
        ->and(MediaTypeEnum::fromPath('venues/1/banner.MOV'))->toBe(MediaTypeEnum::VIDEO)
        ->and(MediaTypeEnum::fromPath('venues/1/gallery.webm'))->toBe(MediaTypeEnum::VIDEO);
});

test('media type defaults to image when extension is missing', function () {
    expect(MediaTypeEnum::fromPath('venues/1/banner'))->toBe(MediaTypeEnum::IMAGE);
});
