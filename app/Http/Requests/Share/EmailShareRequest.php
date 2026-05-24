<?php

namespace App\Http\Requests\Share;

use Illuminate\Foundation\Http\FormRequest;

class EmailShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
