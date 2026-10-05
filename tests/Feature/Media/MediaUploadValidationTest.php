<?php

use App\Models\User;
use App\Services\JwtTokenService;
use Illuminate\Http\UploadedFile;

test('media upload returns laravel validation message and php size limit details when upload exceeds ini size', function () {
    $user = User::factory()->create();
    $token = app(JwtTokenService::class)->issue($user, $user->loginContext());

    $path = tempnam(sys_get_temp_dir(), 'media');
    file_put_contents($path, 'png');

    $file = new UploadedFile(
        $path,
        'testcheck.png',
        'image/png',
        UPLOAD_ERR_INI_SIZE,
        true,
    );

    $this->withToken($token)
        ->post('/api/media/upload', [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('validation.uploaded', ['attribute' => 'media file']))
        ->assertJsonPath(
            'error',
            'PHP UPLOAD_ERR_INI_SIZE: file exceeds upload_max_filesize ('.ini_get('upload_max_filesize').'). post_max_size='.ini_get('post_max_size').'.',
        );

    @unlink($path);
});

test('media upload returns laravel validation message and php details for a partial upload failure', function () {
    $user = User::factory()->create();
    $token = app(JwtTokenService::class)->issue($user, $user->loginContext());

    $path = tempnam(sys_get_temp_dir(), 'media');
    file_put_contents($path, 'png');

    $file = new UploadedFile(
        $path,
        'testcheck.png',
        'image/png',
        UPLOAD_ERR_PARTIAL,
        true,
    );

    $this->withToken($token)
        ->post('/api/media/upload', [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('validation.uploaded', ['attribute' => 'media file']))
        ->assertJsonPath('error', 'PHP UPLOAD_ERR_PARTIAL: file was only partially uploaded.');

    @unlink($path);
});
