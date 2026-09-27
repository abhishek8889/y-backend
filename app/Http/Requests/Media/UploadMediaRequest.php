<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

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
}
