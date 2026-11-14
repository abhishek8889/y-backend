<?php

namespace App\Http\Requests\Organisation;

use App\Enum\NewsStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSiteNewsRequest extends FormRequest
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
            'site_id' => ['nullable', 'integer'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'author' => ['nullable', 'string', 'max:255'],
            'content_role' => ['nullable', 'string', 'max:5000'],
            'headline' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'linked_event_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(NewsStatusEnum::values())],
            // Go-live time. For scheduled, prefer scheduled_to (copied to published_at).
            'published_at' => ['nullable', 'date'],
            'scheduled_to' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = (string) $this->input('status', NewsStatusEnum::DRAFT->value);

            if ($status !== NewsStatusEnum::SCHEDULED->value) {
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
            'site_id' => 'site',
            'featured_image' => 'featured image',
            'author' => 'author',
            'content_role' => 'content role',
            'headline' => 'headline',
            'slug' => 'slug',
            'excerpt' => 'excerpt',
            'description' => 'description',
            'linked_event_id' => 'linked event',
            'status' => 'status',
            'published_at' => 'published at',
            'scheduled_to' => 'scheduled to',
        ];
    }
}
