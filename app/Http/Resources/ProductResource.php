<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'description' => $this->description,
            'image' => $imageUrl,
            'price' => (float) $this->price,
            'tax_id' => $this->tax_id,
            'tax' => new TaxResource($this->whenLoaded('tax')),
            'discount_id' => $this->discount_id,
            'discount' => new DiscountResource($this->whenLoaded('discount')),
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'sub_category_id' => $this->sub_category_id,
            'sub_category' => new CategoryResource($this->whenLoaded('subCategory')),
            'variations' => VariationResource::collection($this->whenLoaded('variations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
