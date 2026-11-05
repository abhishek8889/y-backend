<?php

namespace App\Http\Requests\Organisation;

use App\Enum\SiteBannerFeatureImageTargetEnum;
use App\Enum\SiteBannerFeatureTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSiteBannerRequest extends FormRequest
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
        return [
            'site_id' => ['required', 'integer'],
            'feature_type' => ['required', 'string', Rule::in(SiteBannerFeatureTypeEnum::values())],
            'feature_target_id' => ['nullable', 'integer'],
            'feature_image_target' => [
                'required',
                'string',
                Rule::in(SiteBannerFeatureImageTargetEnum::values()),
            ],
            'custom_headline' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'cta_button_1_label' => ['nullable', 'string', 'max:255'],
            'cta_button_1_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $featureType = SiteBannerFeatureTypeEnum::tryFrom(
                (string) $this->input('feature_type', ''),
            );
            $imageTarget = SiteBannerFeatureImageTargetEnum::tryFrom(
                (string) $this->input('feature_image_target', ''),
            );

            if ($featureType === null || $imageTarget === null) {
                return;
            }

            if (
                $featureType === SiteBannerFeatureTypeEnum::EVENTS
                || $featureType === SiteBannerFeatureTypeEnum::NEWS
            ) {
                if (! filled($this->input('feature_target_id'))) {
                    $validator->errors()->add(
                        'feature_target_id',
                        __('validation.required', ['attribute' => 'feature target']),
                    );
                }
            }

            if ($featureType === SiteBannerFeatureTypeEnum::CUSTOM) {
                if ($imageTarget !== SiteBannerFeatureImageTargetEnum::CUSTOM_UPLOAD) {
                    $validator->errors()->add(
                        'feature_image_target',
                        __('validation.in', ['attribute' => 'feature image target']),
                    );
                }

                if (! filled($this->input('custom_headline'))) {
                    $validator->errors()->add(
                        'custom_headline',
                        __('validation.required', ['attribute' => 'campaign headline']),
                    );
                }

                if (! filled($this->input('image_url'))) {
                    $validator->errors()->add(
                        'image_url',
                        __('validation.required', ['attribute' => 'banner image']),
                    );
                }
            }

            if (
                $featureType !== SiteBannerFeatureTypeEnum::CUSTOM
                && $imageTarget === SiteBannerFeatureImageTargetEnum::CUSTOM_UPLOAD
                && ! filled($this->input('image_url'))
            ) {
                $validator->errors()->add(
                    'image_url',
                    __('validation.required', ['attribute' => 'banner image']),
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
            'site_id' => 'site',
            'feature_type' => 'feature type',
            'feature_target_id' => 'feature target',
            'feature_image_target' => 'feature image target',
            'custom_headline' => 'campaign headline',
            'image_url' => 'banner image',
            'cta_button_1_label' => 'CTA button label',
            'cta_button_1_url' => 'CTA redirect link',
        ];
    }
}
