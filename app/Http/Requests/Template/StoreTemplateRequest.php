<?php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Top-level fields ─────────────────────────────────────────────
            'name'      => ['required', 'string', 'max:255'],
            'is_public' => ['boolean'],
            'preview'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // ── Config object ─────────────────────────────────────────────────
            'config'                   => ['nullable', 'array'],
            'config.width'             => ['nullable', 'integer', 'min:1', 'max:9999'],
            'config.height'            => ['nullable', 'integer', 'min:1', 'max:9999'],
            'config.layout'            => ['nullable', 'string', 'max:50'],
            'config.overlay_url'       => ['nullable', 'url', 'max:2048'],
            'config.background_color'  => ['nullable', 'string', 'max:20'],

            // ── text_elements: array of text layer objects ────────────────────
            'config.text_elements'         => ['nullable', 'array'],
            'config.text_elements.*.text'  => ['required', 'string', 'max:255'],
            'config.text_elements.*.x'     => ['nullable', 'numeric'],
            'config.text_elements.*.y'     => ['nullable', 'numeric'],
            'config.text_elements.*.size'  => ['nullable', 'integer', 'min:8', 'max:200'],
            'config.text_elements.*.color' => ['nullable', 'string', 'max:20'],
            'config.text_elements.*.font'  => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'config.required'                     => 'Template config is required.',
            'config.width.min'                    => 'Canvas width must be at least 1px.',
            'config.width.max'                    => 'Canvas width cannot exceed 9999px.',
            'config.height.min'                   => 'Canvas height must be at least 1px.',
            'config.height.max'                   => 'Canvas height cannot exceed 9999px.',
            'config.overlay_url.url'              => 'Overlay URL must be a valid URL.',
            'config.background_color.max'         => 'Background color value is too long.',
            'config.text_elements.*.text.required' => 'Each text element must have a text value.',
            'config.text_elements.*.size.min'     => 'Font size must be at least 8px.',
            'config.text_elements.*.size.max'     => 'Font size cannot exceed 200px.',
            'preview.image'                       => 'Preview must be an image file.',
            'preview.mimes'                       => 'Preview must be jpg, jpeg, png, or webp.',
            'preview.max'                         => 'Preview image may not exceed 2MB.',
        ];
    }
}
