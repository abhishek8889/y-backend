<?php

namespace App\Http\Requests\Organisation;

use App\Enum\StatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageVenueStatusRequest extends FormRequest
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
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'status' => [
                'required',
                'string',
                Rule::in([
                    StatusEnum::ACTIVE->value,
                    StatusEnum::INACTIVE->value,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'venue_id' => 'venue id',
            'status' => 'venue status',
        ];
    }
}
