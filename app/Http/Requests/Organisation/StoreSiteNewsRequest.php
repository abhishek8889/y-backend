<?php

namespace App\Http\Requests\Organisation;

use App\Enum\NewsStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteNewsRequest extends FormRequest
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
            'site_id' => ['nullable', 'integer'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'headline' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'linked_event_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(NewsStatusEnum::values())],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'headline.required' => __('validation.required'),
            'status.in' => __('validation.in'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_id' => 'site',
            'featured_image' => 'featured image',
            'headline' => 'headline',
            'slug' => 'slug',
            'excerpt' => 'excerpt',
            'description' => 'description',
            'linked_event_id' => 'linked event',
            'status' => 'status',
            'published_at' => 'published at',
        ];
    }
}
