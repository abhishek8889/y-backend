<?php

namespace App\Http\Requests\Organisation;

use App\Enum\SiteStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'domain' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^(?=.{1,255}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
            ],
            'site_template_id' => ['sometimes', 'nullable', 'integer', 'exists:site_templates,id'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'favicon' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'header' => ['sometimes', 'nullable', 'array'],
            'footer' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', 'required', 'string', Rule::in([
                SiteStatusEnum::DRAFT->value,
                SiteStatusEnum::ACTIVE->value,
                SiteStatusEnum::DISABLED->value,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('validation.required'),
            'domain.required' => __('validation.required'),
            'domain.regex' => __('validation.regex'),
            'site_template_id.exists' => __('validation.exists'),
            'status.in' => __('validation.in'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'site name',
            'domain' => 'domain',
            'site_template_id' => 'site template',
            'logo' => 'logo',
            'favicon' => 'favicon',
            'header' => 'header',
            'footer' => 'footer',
            'status' => 'status',
        ];
    }
}
