<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteNewsRequest extends FormRequest
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
            'site_id' => ['sometimes', 'nullable', 'integer'],
            'featured_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'author' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content_role' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'headline' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'excerpt' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'linked_event_id' => ['sometimes', 'nullable', 'integer'],
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
            'author' => 'author',
            'content_role' => 'content role',
            'headline' => 'headline',
            'slug' => 'slug',
            'excerpt' => 'excerpt',
            'description' => 'description',
            'linked_event_id' => 'linked event',
        ];
    }
}
