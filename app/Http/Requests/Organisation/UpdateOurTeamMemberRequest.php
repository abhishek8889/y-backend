<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOurTeamMemberRequest extends FormRequest
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
            'site_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:users,id'],
            'image_url' => ['sometimes', 'nullable'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'about' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'joined_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_id' => 'site',
            'user_id' => 'user',
            'image_url' => 'image',
            'name' => 'name',
            'designation' => 'designation',
            'about' => 'about',
            'joined_at' => 'joined at',
        ];
    }
}
