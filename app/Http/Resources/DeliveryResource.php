<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rawImages = is_array($this->id_images) ? $this->id_images : (json_decode($this->id_images, true) ?? []);

        $imageUrls = array_map(function ($image) {
            if (! $image) {
                return null;
            }
            if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
                return $image;
            }

            return url('storage/'.ltrim($image, '/'));
        }, array_filter($rawImages));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'id_images' => array_values($imageUrls),
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
