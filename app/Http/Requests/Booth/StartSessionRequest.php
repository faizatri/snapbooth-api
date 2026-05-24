<?php

namespace App\Http\Requests\Booth;

use Illuminate\Foundation\Http\FormRequest;

class StartSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_slug'  => ['required', 'string', 'max:100'],
            'guest_name'  => ['nullable', 'string', 'max:100'],
            'guest_email' => ['nullable', 'email', 'max:150'],
        ];
    }
}
