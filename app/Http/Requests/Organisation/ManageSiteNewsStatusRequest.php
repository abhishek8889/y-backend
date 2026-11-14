<?php

namespace App\Http\Requests\Organisation;

use App\Enum\NewsStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ManageSiteNewsStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(NewsStatusEnum::values())],
            'published_at' => ['nullable', 'date'],
            'scheduled_to' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('status') !== NewsStatusEnum::SCHEDULED->value) {
                return;
            }

            if (! filled($this->input('scheduled_to')) && ! filled($this->input('published_at'))) {
                $validator->errors()->add(
                    'scheduled_to',
                    __('validation.required', ['attribute' => 'scheduled to']),
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'status',
            'published_at' => 'published at',
            'scheduled_to' => 'scheduled to',
        ];
    }
}
