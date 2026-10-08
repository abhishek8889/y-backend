<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StartBookingTicketRequest extends FormRequest
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
        $isGuest = ! filled($this->bearerToken());

        return [
            'offer_id' => ['required', 'array', 'min:1'],
            'offer_id.*' => ['required', 'integer', 'distinct', 'exists:event_ticket_offers,id'],
            'name' => [$isGuest ? 'required' : 'nullable', 'string', 'max:255'],
            'email' => [$isGuest ? 'required' : 'nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'offer_id.required' => __('validation.required'),
            'offer_id.array' => __('validation.array'),
            'offer_id.min' => __('validation.min.array'),
            'offer_id.*.required' => __('validation.required'),
            'offer_id.*.integer' => __('validation.integer'),
            'offer_id.*.distinct' => __('validation.distinct'),
            'offer_id.*.exists' => __('validation.exists'),
            'name.required' => __('validation.required'),
            'name.string' => __('validation.string'),
            'name.max' => __('validation.max.string'),
            'email.required' => __('validation.required'),
            'email.string' => __('validation.string'),
            'email.email' => __('validation.email'),
            'email.max' => __('validation.max.string'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'offer_id' => 'offer ids',
            'offer_id.*' => 'offer id',
            'name' => 'name',
            'email' => 'email',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge([
                'email' => Str::lower($this->string('email')->toString()),
            ]);
        }
    }
}
