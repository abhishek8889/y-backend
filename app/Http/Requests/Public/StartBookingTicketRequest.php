<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
        $isGuest = ! filled($this->bearerToken());

        return [
            'offers' => ['required', 'array', 'min:1'],
            'offers.*.offer_id' => ['required', 'integer', 'exists:event_ticket_offers,id'],
            'offers.*.qty' => ['required', 'integer', 'min:1'],
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
            'offers.required' => __('validation.required'),
            'offers.array' => __('validation.array'),
            'offers.min' => __('validation.min.array'),
            'offers.*.offer_id.required' => __('validation.required'),
            'offers.*.offer_id.integer' => __('validation.integer'),
            'offers.*.offer_id.exists' => __('validation.exists'),
            'offers.*.qty.required' => __('validation.required'),
            'offers.*.qty.integer' => __('validation.integer'),
            'offers.*.qty.min' => __('validation.min.numeric'),
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
            'offers' => 'offers',
            'offers.*.offer_id' => 'offer id',
            'offers.*.qty' => 'quantity',
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
