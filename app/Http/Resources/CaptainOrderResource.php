<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaptainOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rawImages = is_array($this->images)
            ? $this->images
            : (json_decode($this->images ?? '[]', true) ?: []);

        $imagesUrls = array_values(array_filter(array_map(function ($image) {
            if (! $image) {
                return null;
            }

            if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
                return $image;
            }

            return url('storage/'.ltrim($image, '/'));
        }, $rawImages)));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'user_name' => $this->user_name,
            'status' => (bool) $this->status,
            'images' => $imagesUrls,
            'raw_images' => $rawImages,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
