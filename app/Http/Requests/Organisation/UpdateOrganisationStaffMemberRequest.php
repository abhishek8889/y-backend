<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UpdateOrganisationStaffMemberRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'country_code' => ['required', 'string', 'max:10'],
            'country' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', Password::defaults()],
            'confirm_password' => ['required_with:password', 'nullable', 'string', 'same:password'],
            'description' => ['nullable', 'string', 'max:1000'],
            'profile_image' => ['nullable', 'string', 'max:500'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => __('validation.required'),
            'last_name.required' => __('validation.required'),
            'email.required' => __('validation.required'),
            'email.email' => __('validation.email'),
            'phone.required' => __('validation.required'),
            'country_code.required' => __('validation.required'),
            'country.required' => __('validation.required'),
            'confirm_password.required_with' => __('validation.required'),
            'confirm_password.same' => __('validation.same'),
            'role_ids.required' => __('validation.required'),
            'role_ids.min' => __('validation.min.array'),
            'role_ids.*.exists' => __('validation.exists'),
            'status.in' => __('validation.in'),
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
            'description' => 'description',
            'profile_image' => 'profile image',
            'role_ids' => 'roles',
            'role_ids.*' => 'role',
            'status' => 'status',
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
