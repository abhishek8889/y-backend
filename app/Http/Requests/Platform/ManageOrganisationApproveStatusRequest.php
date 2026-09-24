<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageOrganisationApproveStatusRequest extends FormRequest
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
            'organisation_id' => ['required', 'integer', 'exists:organisations,id'],
            'approve_status' => ['required', 'boolean'],
            'approve_status_reason' => [
                Rule::requiredIf(fn (): bool => $this->boolean('approve_status') === false),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'organisation_id.required' => __('validation.required'),
            'organisation_id.integer' => __('validation.integer'),
            'organisation_id.exists' => __('messages.organisation_not_found'),
            'approve_status.required' => __('validation.required'),
            'approve_status.boolean' => __('validation.boolean'),
            'approve_status_reason.required' => __('validation.required'),
            'approve_status_reason.string' => __('validation.string'),
            'approve_status_reason.max' => __('validation.max.string'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organisation_id' => 'organisation',
            'approve_status' => 'approve status',
            'approve_status_reason' => 'rejection reason',
        ];
    }
}
