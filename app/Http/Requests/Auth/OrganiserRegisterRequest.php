<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class OrganiserRegisterRequest extends FormRequest
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
            // Step 1 — personal
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'phone' => ['required', 'string', 'max:30'],
            'country_code' => ['required', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', Password::defaults()],
            'confirm_password' => ['required', 'string', 'same:password'],

            // Step 2 — organisation (`org_` mirrors organisations table)
            'org_organiser_name' => ['nullable', 'string', 'max:255'],
            'org_name' => ['required', 'string', 'max:255'],
            'org_email' => ['nullable', 'string', 'email', 'max:255'],
            'org_country_calling_code' => ['required', 'string', 'max:10'],
            'org_country_code' => ['required', 'string', 'size:2'],
            'org_phone' => ['nullable', 'string', 'max:30'],
            'org_country' => ['nullable', 'string', 'max:100'],
            'org_city' => ['nullable', 'string', 'max:255'],
            'org_address1' => ['nullable', 'string', 'max:255'],
            'org_address2' => ['nullable', 'string', 'max:255'],
            'org_postal_code' => ['nullable', 'string', 'max:255'],
            'org_website' => ['nullable', 'string', 'max:255'],
            'org_logo' => ['nullable', 'string', 'max:255'],
            'org_banner' => ['nullable', 'string', 'max:255'],
            'org_description' => ['nullable', 'string', 'max:255'],
            'org_keywords' => ['nullable', 'string', 'max:255'],
            'org_facebook_link' => ['nullable', 'string', 'max:255'],
            'org_instagram_link' => ['nullable', 'string', 'max:255'],
            'org_twitter_link' => ['nullable', 'string', 'max:255'],
            'org_youtube_link' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => __('validation.required'),
            'first_name.string' => __('validation.string'),
            'first_name.max' => __('validation.max.string'),
            'last_name.required' => __('validation.required'),
            'last_name.string' => __('validation.string'),
            'last_name.max' => __('validation.max.string'),
            'email.required' => __('validation.required'),
            'email.string' => __('validation.string'),
            'email.email' => __('validation.email'),
            'email.max' => __('validation.max.string'),
            'phone.required' => __('validation.required'),
            'phone.string' => __('validation.string'),
            'phone.max' => __('validation.max.string'),
            'country_code.required' => __('validation.required'),
            'country_code.string' => __('validation.string'),
            'country_code.max' => __('validation.max.string'),
            'country.required' => __('validation.required'),
            'country.string' => __('validation.string'),
            'country.max' => __('validation.max.string'),
            'password.required' => __('validation.required'),
            'password.string' => __('validation.string'),
            'confirm_password.required' => __('validation.required'),
            'confirm_password.string' => __('validation.string'),
            'confirm_password.same' => __('validation.same'),
            'org_name.required' => __('validation.required'),
            'org_name.string' => __('validation.string'),
            'org_name.max' => __('validation.max.string'),
            'org_email.email' => __('validation.email'),
            'org_country_calling_code.required' => __('validation.required'),
            'org_country_calling_code.string' => __('validation.string'),
            'org_country_calling_code.max' => __('validation.max.string'),
            'org_country_code.required' => __('validation.required'),
            'org_country_code.string' => __('validation.string'),
            'org_country_code.size' => __('validation.size.string'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => __('validation.attributes.first_name'),
            'last_name' => __('validation.attributes.last_name'),
            'email' => __('validation.attributes.email'),
            'phone' => __('validation.attributes.phone'),
            'country_code' => __('validation.attributes.country_code'),
            'country' => __('validation.attributes.country'),
            'password' => __('validation.attributes.password'),
            'confirm_password' => __('validation.attributes.confirm_password'),
            'org_name' => 'organisation name',
            'org_email' => 'organisation email',
            'org_country_calling_code' => __('validation.attributes.org_country_calling_code'),
            'org_country_code' => __('validation.attributes.org_country_code'),
            'org_phone' => 'organisation phone',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('email')) {
            $merge['email'] = Str::lower($this->string('email')->toString());
        }

        if ($this->filled('org_email')) {
            $merge['org_email'] = Str::lower($this->string('org_email')->toString());
        }

        if ($this->filled('org_country_code')) {
            $merge['org_country_code'] = Str::upper($this->string('org_country_code')->toString());
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
