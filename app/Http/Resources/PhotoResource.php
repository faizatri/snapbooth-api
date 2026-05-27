<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'url'           => $this->file_url,
            'thumbnail_url' => $this->thumbnail_url,
            'processed_url' => $this->file_url,
            'shot_number'   => $this->shot_number,
            'is_shared'     => $this->is_shared,
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
