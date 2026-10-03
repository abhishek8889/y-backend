<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganisationRoleRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('validation.required'),
            'name.string' => __('validation.string'),
            'name.max' => __('validation.max.string'),
            'description.string' => __('validation.string'),
            'description.max' => __('validation.max.string'),
            'permission_ids.array' => __('validation.array'),
            'permission_ids.*.integer' => __('validation.integer'),
            'permission_ids.*.distinct' => __('validation.distinct'),
            'permission_ids.*.exists' => __('validation.exists'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'role name',
            'description' => 'role description',
            'permission_ids' => 'permissions',
            'permission_ids.*' => 'permission',
        ];
    }
}
