<?php

namespace App\Http\Requests\Booth;

use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => ['required', 'string', 'size:64'],
            'photo'         => ['required', 'string'],
            'shot_number'   => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Photo data (base64) is required.',
            'photo.string'   => 'Photo must be a base64-encoded string.',
        ];
    }
}
