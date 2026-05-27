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
            'photo'         => ['required'],
            'shot_number'   => ['sometimes', 'integer', 'min:1', 'max:99'],
        ];
    }
}
