<?php

namespace App\Http\Resources;

use App\Models\Option;
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
            'product_manufacturing' => $this->when(
                $this->relationLoaded('productManufacturing'),
                fn () => $this->productManufacturing ? new ProductManufacturingResource($this->productManufacturing) : null
            ),
            'recipes' => $this->when(
                $this->relationLoaded('productManufacturing'),
                fn () => $this->productManufacturing && $this->productManufacturing->relationLoaded('productRecipeManufacturings')
                    ? ProductRecipeManufacturingResource::collection($this->productManufacturing->productRecipeManufacturings)
                    : []
            ),
            'option_recipes' => $this->when(
                $this->relationLoaded('productManufacturings'),
                fn () => $this->buildOptionRecipes()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Build the option_recipes array from combination-keyed product manufacturings.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildOptionRecipes(): array
    {
        if (! $this->relationLoaded('productManufacturings')) {
            return [];
        }

        return $this->productManufacturings
            ->filter(fn ($pm) => $pm->option_combination_key !== null)
            ->map(function ($pm) {
                $optionIds = array_map('intval', explode(',', $pm->option_combination_key));
                $options = Option::whereIn('id', $optionIds)->get();

                $optionNames = [
                    'ar' => $options->map(fn ($o) => is_array($o->name) ? ($o->name['ar'] ?? '') : $o->name)->implode(' + '),
                    'en' => $options->map(fn ($o) => is_array($o->name) ? ($o->name['en'] ?? '') : $o->name)->implode(' + '),
                ];

                return [
                    'id' => $pm->id,
                    'option_combination_key' => $pm->option_combination_key,
                    'option_ids' => $optionIds,
                    'option_names' => $optionNames,
                    'recipes' => $pm->relationLoaded('productRecipeManufacturings')
                        ? ProductRecipeManufacturingResource::collection($pm->productRecipeManufacturings)
                        : [],
                ];
            })
            ->values()
            ->all();
    }
}
