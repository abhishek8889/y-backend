<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
        return [
            'offers' => ['required', 'array', 'min:1'],
            'offers.*.offer_id' => ['required', 'integer', 'exists:event_ticket_offers,id'],
            'offers.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'offers.required' => __('validation.required'),
            'offers.array' => __('validation.array'),
            'offers.min' => __('validation.min.array'),
            'offers.*.offer_id.required' => __('validation.required'),
            'offers.*.offer_id.integer' => __('validation.integer'),
            'offers.*.offer_id.exists' => __('validation.exists'),
            'offers.*.qty.required' => __('validation.required'),
            'offers.*.qty.integer' => __('validation.integer'),
            'offers.*.qty.min' => __('validation.min.numeric'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'offers' => 'offers',
            'offers.*.offer_id' => 'offer id',
            'offers.*.qty' => 'quantity',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $offers = $this->input('offers');

            if (! is_array($offers)) {
                return;
            }

            $offerIds = collect($offers)
                ->pluck('offer_id')
                ->filter(fn ($id): bool => filled($id))
                ->map(fn ($id): int => (int) $id);

            if ($offerIds->count() !== $offerIds->unique()->count()) {
                $validator->errors()->add('offers', 'Duplicate offer ids are not allowed.');
            }
        });
    }
}
