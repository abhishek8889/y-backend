<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOurTeamMemberRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'image_url' => ['nullable'],
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:5000'],
            'joined_at' => ['nullable', 'date'],
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
