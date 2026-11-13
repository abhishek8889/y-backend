<?php

namespace App\Http\Requests\Organisation;

use App\Enum\StatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteSupportContactRequest extends FormRequest
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
            'site_id' => ['sometimes', 'required', 'integer'],
            'department_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'required', 'string', Rule::in([
                StatusEnum::ACTIVE->value,
                StatusEnum::INACTIVE->value,
            ])],
            'show_in_footer' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_name.required' => __('validation.required'),
            'email.email' => __('validation.email'),
            'status.in' => __('validation.in'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_id' => 'site',
            'department_name' => 'department name',
            'email' => 'email',
            'phone' => 'phone',
            'status' => 'status',
            'show_in_footer' => 'show in footer',
        ];
    }
}
