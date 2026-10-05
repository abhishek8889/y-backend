<?php

namespace App\Http\Requests\Media;

use App\Exceptions\ServiceException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $imageMimes = implode(',', config('media.image_mimes', []));
        $videoMimes = implode(',', config('media.video_mimes', []));
        $maxKilobytes = max(
            (int) config('media.max_image_kilobytes', 10240),
            (int) config('media.max_video_kilobytes', 102400),
        );

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                'mimes:'.$imageMimes.','.$videoMimes,
            ],
            'context' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var UploadedFile|null $file */
            $file = $this->file('file');

            if ($file === null || ! $file->isValid()) {
                return;
            }

            $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));
            $imageMimes = array_map('strtolower', config('media.image_mimes', []));
            $videoMimes = array_map('strtolower', config('media.video_mimes', []));

            $maxKilobytes = in_array($extension, $videoMimes, true)
                ? (int) config('media.max_video_kilobytes', 102400)
                : (int) config('media.max_image_kilobytes', 10240);

            if (($file->getSize() ?: 0) > $maxKilobytes * 1024) {
                $validator->errors()->add(
                    'file',
                    __('validation.max.file', ['attribute' => 'file', 'max' => $maxKilobytes]),
                );
            }

            if (
                ! in_array($extension, $imageMimes, true)
                && ! in_array($extension, $videoMimes, true)
            ) {
                $validator->errors()->add(
                    'file',
                    __('validation.mimes', [
                        'attribute' => 'file',
                        'values' => implode(', ', array_merge($imageMimes, $videoMimes)),
                    ]),
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'media file',
            'context' => 'media context',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        /** @var UploadedFile|null $file */
        $file = $this->file('file');

        $message = $validator->errors()->first()
            ?: __('validation.uploaded', ['attribute' => 'media file']);

        $error = $message;

        if ($file instanceof UploadedFile && ! $file->isValid()) {
            $error = $this->phpUploadErrorDetails($file);
        }

        throw new ServiceException(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $message,
            $error,
        );
    }

    private function phpUploadErrorDetails(UploadedFile $file): string
    {
        $uploadMaxFilesize = (string) ini_get('upload_max_filesize');
        $postMaxSize = (string) ini_get('post_max_size');

        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE => "PHP UPLOAD_ERR_INI_SIZE: file exceeds upload_max_filesize ({$uploadMaxFilesize}). post_max_size={$postMaxSize}.",
            UPLOAD_ERR_FORM_SIZE => 'PHP UPLOAD_ERR_FORM_SIZE: file exceeds the form MAX_FILE_SIZE limit.',
            UPLOAD_ERR_PARTIAL => 'PHP UPLOAD_ERR_PARTIAL: file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'PHP UPLOAD_ERR_NO_FILE: no file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP UPLOAD_ERR_NO_TMP_DIR: missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'PHP UPLOAD_ERR_CANT_WRITE: failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'PHP UPLOAD_ERR_EXTENSION: a PHP extension stopped the file upload.',
            default => "PHP upload error code {$file->getError()}. upload_max_filesize={$uploadMaxFilesize}, post_max_size={$postMaxSize}.",
        };
    }
}
