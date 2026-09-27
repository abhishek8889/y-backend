<?php

namespace App\Http\Requests\Organisation;

use App\Enum\EventTicketStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventTicketRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'quantity_cap' => ['required', 'integer', 'min:1'],
            'badge_label' => ['nullable', 'string'],
            'entitlement' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(EventTicketStatusEnum::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ticket name',
            'base_price' => 'base price',
            'quantity_cap' => 'quantity',
            'status' => 'ticket status',
        ];
    }
}
