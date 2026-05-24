<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                            => ['required', 'string', 'max:255'],
            'date'                            => ['required', 'date'],
            'location'                        => ['nullable', 'string', 'max:255'],
            'is_active'                       => ['boolean'],

            'booth_config'                    => ['nullable', 'array'],
            'booth_config.countdown'          => ['nullable', 'integer', 'min:1', 'max:60'],
            'booth_config.photos_per_session' => ['nullable', 'integer', 'min:1', 'max:10'],
            'booth_config.filter'             => ['nullable', 'string', 'max:50'],
            'booth_config.template_id'        => ['nullable', 'integer', 'exists:templates,id'],
            'booth_config.share_options'      => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'booth_config.countdown.min'          => 'Countdown must be at least 1 second.',
            'booth_config.countdown.max'          => 'Countdown cannot exceed 60 seconds.',
            'booth_config.photos_per_session.min' => 'Photos per session must be at least 1.',
            'booth_config.photos_per_session.max' => 'Photos per session cannot exceed 10.',
            'booth_config.template_id.exists'     => 'Selected template does not exist.',
        ];
    }
}
