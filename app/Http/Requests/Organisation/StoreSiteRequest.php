<?php

namespace App\Http\Requests\Organisation;

use App\Enum\SiteTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
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
            'domain' => ['required', 'string', 'max:255', 'regex:/^(?=.{1,255}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i'],
            'site_type' => ['required', 'string', Rule::in([
                SiteTypeEnum::CLUB->value,
                SiteTypeEnum::WHITE_LABEL->value,
            ])],
            'site_template_id' => ['nullable', 'integer', 'exists:site_templates,id'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'favicon' => ['nullable', 'string', 'max:2048'],
            'header' => ['nullable', 'array'],
            'footer' => ['nullable', 'array'],
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
            'site_type.required' => __('validation.required'),
            'site_type.in' => __('validation.in'),
            'site_template_id.exists' => __('validation.exists'),
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
            'site_type' => 'site type',
            'site_template_id' => 'site template',
            'logo' => 'logo',
            'favicon' => 'favicon',
            'header' => 'header',
            'footer' => 'footer',
        ];
    }
}
