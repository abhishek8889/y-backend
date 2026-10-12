<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWishlistRequest extends FormRequest
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
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'organisation_id' => ['nullable', 'integer', 'exists:organisations,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_id.required' => __('validation.required'),
            'event_id.integer' => __('validation.integer'),
            'event_id.exists' => __('validation.exists'),
            'organisation_id.integer' => __('validation.integer'),
            'organisation_id.exists' => __('validation.exists'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'event_id' => 'event',
            'organisation_id' => 'organisation',
        ];
    }
}
