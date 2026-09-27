<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventTicketOfferRequest extends FormRequest
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
            'ticket_id' => ['required', 'integer', 'exists:event_tickets,id'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity_cap' => ['required', 'integer', 'min:1'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after:sale_starts_at'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'access' => ['nullable', 'string', Rule::in(['public', 'private', 'invite_only'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ticket_id' => 'ticket',
            'name' => 'offer name',
            'price' => 'offer price',
            'quantity_cap' => 'offer quantity',
            'sale_starts_at' => 'sale start',
            'sale_ends_at' => 'sale end',
        ];
    }
}
