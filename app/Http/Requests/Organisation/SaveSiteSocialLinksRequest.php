<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveSiteSocialLinksRequest extends FormRequest
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
            'site_id' => ['required', 'integer', 'min:1'],
            'social_links' => ['required', 'array'],
            'social_links.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_id' => 'site',
            'social_links' => 'social links',
            'social_links.*' => 'social link',
        ];
    }
}
