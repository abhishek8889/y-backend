<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'event_category_id' => ['nullable', 'integer', 'exists:event_categories,id'],
            'description' => ['nullable', 'string'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'string', 'max:100'],

            'images' => ['nullable', 'array'],
            'images.*.id' => ['nullable', 'integer', 'exists:event_images,id'],
            'images.*.type' => ['required', 'string', Rule::in(['banner', 'gallery'])],
            'images.*.path' => ['required', 'string', 'max:500'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'event title',
            'event_category_id' => 'event category',
            'venue_id' => 'venue',
            'starts_at' => 'start date and time',
            'ends_at' => 'end date and time',
            'images.*.type' => 'image type',
            'images.*.path' => 'image path',
        ];
    }
}
