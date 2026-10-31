<?php

namespace App\Services;

use App\Models\AddonStock;
use App\Models\MaterialStock;
use App\Models\ProductRecipeStock;
use App\Models\Unit;

class UnitConversionService
{
    /**
     * Local in-memory cache for unit values.
     *
     * @var array<int, float>
     */
    protected static array $unitValues = [];

    /**
     * Convert quantity from source unit to target unit.
     *
     * Formula:
     * converted_quantity = quantity * (source_unit_value / target_unit_value)
     *
     * Example:
     * 2 kg (value=1000) converted to gram (value=1):
     * 2 * (1000 / 1) = 2000 grams.
     *
     * 500 grams (value=1) converted to kg (value=1000):
     * 500 * (1 / 1000) = 0.5 kg.
     */
    public static function convert(float $quantity, ?int $sourceUnitId, ?int $targetUnitId): float
    {
        if ($quantity == 0.0) {
            return 0.0;
        }

        if ($sourceUnitId === null || $targetUnitId === null || $sourceUnitId === $targetUnitId) {
            return $quantity;
        }

        $sourceValue = self::getUnitValue($sourceUnitId);
        $targetValue = self::getUnitValue($targetUnitId);

        if ($targetValue <= 0.0 || $sourceValue <= 0.0) {
            return $quantity;
        }

        return (float) ($quantity * ($sourceValue / $targetValue));
    }

    /**
     * Convert a quantity from the specified source unit to the target MaterialStock's unit.
     */
    public static function toMaterialStockUnit(float $quantity, ?int $sourceUnitId, int $materialId, int $branchId): float
    {
        if ($sourceUnitId === null) {
            return $quantity;
        }

        $targetUnitId = MaterialStock::where('material_id', $materialId)
            ->where('branch_id', $branchId)
            ->value('unit_id');

        return self::convert($quantity, $sourceUnitId, $targetUnitId);
    }

    /**
     * Convert a quantity from the specified source unit to the target ProductRecipeStock's unit.
     */
    public static function toRecipeStockUnit(float $quantity, ?int $sourceUnitId, int $recipeId, int $branchId): float
    {
        if ($sourceUnitId === null) {
            return $quantity;
        }

        $targetUnitId = ProductRecipeStock::where('product_recipe_id', $recipeId)
            ->where('branch_id', $branchId)
            ->value('unit_id');

        return self::convert($quantity, $sourceUnitId, $targetUnitId);
    }

    /**
     * Convert a quantity from the specified source unit to the target AddonStock's unit.
     */
    public static function toAddonStockUnit(float $quantity, ?int $sourceUnitId, int $addonId, int $branchId): float
    {
        if ($sourceUnitId === null) {
            return $quantity;
        }

        $targetUnitId = AddonStock::where('addon_id', $addonId)
            ->where('branch_id', $branchId)
            ->value('unit_id');

        return self::convert($quantity, $sourceUnitId, $targetUnitId);
    }

    /**
     * Get numeric multiplier value for a unit.
     */
    public static function getUnitValue(?int $unitId): float
    {
        if (! $unitId) {
            return 1.0;
        }

        if (isset(self::$unitValues[$unitId])) {
            return self::$unitValues[$unitId];
        }

        $value = (float) (Unit::where('id', $unitId)->value('value') ?? 1.0);
        self::$unitValues[$unitId] = $value > 0 ? $value : 1.0;

        return self::$unitValues[$unitId];
    }

    /**
     * Clear cached unit values (useful in testing).
     */
    public static function clearCache(): void
    {
        self::$unitValues = [];
    }
}
