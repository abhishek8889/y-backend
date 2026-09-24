<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVenueRequest extends FormRequest
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
            'venue_type_id' => ['nullable', 'integer', 'exists:venue_types,id'],
            'description' => ['nullable', 'string'],
            'maximum_capacity' => ['nullable', 'integer', 'min:0'],
            'standing_capacity' => ['nullable', 'integer', 'min:0'],
            'seated_capacity' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'is_private_hire_available' => ['nullable', 'boolean'],
            'private_hire_description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'venue name',
            'venue_type_id' => 'venue type',
            'is_private_hire_available' => 'private hire available',
            'private_hire_description' => 'private hire description',
        ];
    }
}
