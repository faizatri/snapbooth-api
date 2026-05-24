<?php

namespace App\Http\Requests\Booth;

use Illuminate\Foundation\Http\FormRequest;

class CompleteSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token'        => ['required', 'string', 'size:64'],
            'selected_photo_ids'   => ['required', 'array', 'min:1'],
            'selected_photo_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'selected_photo_ids.required' => 'At least one photo must be selected.',
            'selected_photo_ids.min'      => 'At least one photo must be selected.',
        ];
    }
}
