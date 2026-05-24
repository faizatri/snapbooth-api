<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'file_url'   => $this->file_url,
            'is_shared'  => $this->is_shared,
            'metadata'   => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
