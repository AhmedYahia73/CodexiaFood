<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageUrl = null;
        if ($this->image) {
            if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
                $imageUrl = $this->image;
            } else {
                $imageUrl = url('storage/'.ltrim($this->image, '/'));
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'image' => $imageUrl,
            'tax_id' => $this->tax_id,
            'tax' => new TaxResource($this->whenLoaded('tax')),
            'discount_id' => $this->discount_id,
            'discount' => new DiscountResource($this->whenLoaded('discount')),
            'kitchen_id' => $this->kitchen_id,
            'kitchen' => new KitchenResource($this->whenLoaded('kitchen')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
