<?php

namespace App\Http\Requests\Organisation;

use App\Enum\SiteCategoryTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteCategoryRequest extends FormRequest
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
            'feature_object' => ['required', 'array'],
            'feature_object.event_category' => ['required', 'string', Rule::in(['on', 'off'])],
            'feature_object.news_category' => ['required', 'string', Rule::in(['on', 'off'])],
            'feature_object.team_member_category' => ['required', 'string', Rule::in(['on', 'off'])],
            'categories' => ['nullable', 'array'],
            'categories.*.category_type' => [
                'required',
                'string',
                Rule::in(SiteCategoryTypeEnum::values()),
            ],
            'categories.*.category_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_id' => 'site',
            'feature_object' => 'feature toggles',
            'feature_object.event_category' => 'events section',
            'feature_object.news_category' => 'news section',
            'feature_object.team_member_category' => 'team members section',
            'categories' => 'categories',
            'categories.*.category_type' => 'category type',
            'categories.*.category_id' => 'category',
        ];
    }
}
