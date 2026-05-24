<?php

namespace App\Http\Requests\Share;

use Illuminate\Foundation\Http\FormRequest;

class LinkShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => ['required', 'string', 'size:64'],
        ];
    }
}
