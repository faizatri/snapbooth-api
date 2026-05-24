<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'preview_url' => $this->preview_url,
            'is_public'   => $this->is_public,
            'is_system'   => $this->isSystemTemplate(),
            'config'      => $this->resolveConfig(),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }

    private function resolveConfig(): array
    {
        $c = $this->config ?? [];

        return [
            'width'            => data_get($c, 'width'),
            'height'           => data_get($c, 'height'),
            'layout'           => data_get($c, 'layout'),
            'overlay_url'      => data_get($c, 'overlay_url'),
            'background_color' => data_get($c, 'background_color'),
            'text_elements'    => data_get($c, 'text_elements', []),
        ];
    }
}
