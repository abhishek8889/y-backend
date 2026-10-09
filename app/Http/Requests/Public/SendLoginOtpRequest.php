<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SendLoginOtpRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('validation.required'),
            'email.string' => __('validation.string'),
            'email.email' => __('validation.email'),
            'email.max' => __('validation.max.string'),
            'phone.required' => __('validation.required'),
            'phone.string' => __('validation.string'),
            'phone.max' => __('validation.max.string'),
            'first_name.string' => __('validation.string'),
            'first_name.max' => __('validation.max.string'),
            'last_name.string' => __('validation.string'),
            'last_name.max' => __('validation.max.string'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('validation.attributes.email'),
            'phone' => __('validation.attributes.phone'),
            'first_name' => __('validation.attributes.first_name'),
            'last_name' => __('validation.attributes.last_name'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower($this->string('email')->toString()),
            ]);
        }
    }
}
