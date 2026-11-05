<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResolveHostnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('hostname')) {
            return;
        }

        $fallback = $this->hostFromHeader(
            $this->headers->get('Origin') ?? $this->headers->get('Referer'),
        );

        if ($fallback !== null) {
            $this->merge(['hostname' => $fallback]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hostname.required' => __('validation.required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'hostname' => 'hostname',
        ];
    }

    private function hostFromHeader(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $host = parse_url(trim($value), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
