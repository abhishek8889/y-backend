<?php

namespace App\Http\Requests\Organisation;

use App\Enum\StatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteSupportContactRequest extends FormRequest
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
            'site_id' => ['required', 'integer'],
            'department_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in([
                StatusEnum::ACTIVE->value,
                StatusEnum::INACTIVE->value,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_id.required' => __('validation.required'),
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
        ];
    }
}
