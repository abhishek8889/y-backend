<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            // Domain hostname only (e.g. club.example.com) — not a URL with scheme/path.
            'hostname' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?=.{1,255}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
                'not_regex:/^https?:\/\//i',
                'not_regex:/[\/?#]/',
            ],
            'template_id' => ['nullable', 'integer', 'exists:site_templates,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('validation.required'),
            'hostname.required' => __('validation.required'),
            'hostname.regex' => 'Hostname must be a domain name only (e.g. club.example.com), not a URL.',
            'hostname.not_regex' => 'Hostname must be a domain name only (e.g. club.example.com), not a URL.',
            'template_id.exists' => __('validation.exists'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'site name',
            'hostname' => 'hostname',
            'template_id' => 'site template',
        ];
    }
}
