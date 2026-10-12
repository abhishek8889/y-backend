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

    protected function prepareForValidation(): void
    {
        if ($this->has('permissions') && ! $this->has('permission_ids')) {
            $this->merge([
                'permission_ids' => $this->input('permissions'),
            ]);
        }
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
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
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
            'permissions.array' => __('validation.array'),
            'permissions.*.integer' => __('validation.integer'),
            'permissions.*.distinct' => __('validation.distinct'),
            'permissions.*.exists' => __('validation.exists'),
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
            'permissions' => 'permissions',
            'permissions.*' => 'permission',
        ];
    }
}
