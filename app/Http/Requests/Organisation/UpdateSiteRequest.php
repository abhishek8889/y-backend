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
     * Only keys present in the request are validated and applied.
     *
     * Fixed navigation item shape stored in sites.navigation:
     * { "name": string, "redirect_link": string }
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'navigation' => ['sometimes', 'nullable', 'array'],
            'navigation.*.name' => ['required', 'string', 'max:255'],
            'navigation.*.redirect_link' => ['required', 'string', 'max:2048'],
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
            'navigation.*.name.required' => 'Each navigation item needs a name.',
            'navigation.*.redirect_link.required' => 'Each navigation item needs a redirect_link.',
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
            'logo' => 'logo',
            'navigation' => 'navigation',
            'status' => 'status',
        ];
    }
}
