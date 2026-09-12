<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Http\Request;

class GeofenceService
{
    /**
     * Determine if a point (lat, lng) is inside a polygon using Ray-Casting algorithm.
     *
     * @param  array<int, array{lat?: float|string, lng?: float|string, latitude?: float|string, longitude?: float|string}|array<int, float|string>>  $polygon
     */
    public function isPointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $points = array_values(array_filter($polygon, function ($point) {
            if (! is_array($point)) {
                return false;
            }

            $hasLat = isset($point['lat']) || isset($point['latitude']) || isset($point[0]);
            $hasLng = isset($point['lng']) || isset($point['longitude']) || isset($point[1]);

            return $hasLat && $hasLng;
        }));

        $numPoints = count($points);
        if ($numPoints < 3) {
            return true;
        }

        $inside = false;

        for ($i = 0, $j = $numPoints - 1; $i < $numPoints; $j = $i++) {
            $pi = $points[$i];
            $pj = $points[$j];

            $xi = (float) ($pi['lat'] ?? $pi['latitude'] ?? $pi[0] ?? 0);
            $yi = (float) ($pi['lng'] ?? $pi['longitude'] ?? $pi[1] ?? 0);
            $xj = (float) ($pj['lat'] ?? $pj['latitude'] ?? $pj[0] ?? 0);
            $yj = (float) ($pj['lng'] ?? $pj['longitude'] ?? $pj[1] ?? 0);

            // Check if horizontal ray crosses the segment between (xi, yi) and (xj, yj)
            if (($yi > $lng) !== ($yj > $lng)) {
                $intersectX = ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi;
                if ($lat < $intersectX) {
                    $inside = ! $inside;
                }
            }
        }

        return $inside;
    }

    /**
     * Extract user coordinates from request query, body, or headers.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function getUserCoordinates(Request $request): ?array
    {
        $lat = $request->input('lat')
            ?? $request->input('latitude')
            ?? $request->header('X-Lat')
            ?? $request->header('X-Latitude')
            ?? $request->header('lat')
            ?? $request->header('latitude');

        $lng = $request->input('lng')
            ?? $request->input('longitude')
            ?? $request->header('X-Lng')
            ?? $request->header('X-Longitude')
            ?? $request->header('lng')
            ?? $request->header('longitude');

        if ($lat === null || $lng === null) {
            return null;
        }

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
        ];
    }

    /**
     * Validate user location against branch location polygon.
     *
     * @return string|null Error message if invalid, null if valid
     */
    public function validateLocation(Request $request, ?Branch $branch): ?string
    {
        if (! $branch) {
            return null;
        }

        $polygon = $branch->location;
        if (is_string($polygon)) {
            $polygon = json_decode($polygon, true);
        }

        if (! is_array($polygon) || count($polygon) < 3) {
            return null;
        }

        $coords = $this->getUserCoordinates($request);
        if ($coords === null) {
            return 'يرجى تحديد الموقع الجغرافي الخاص بك (lat, lng)';
        }

        if (! $this->isPointInPolygon($coords['lat'], $coords['lng'], $polygon)) {
            return 'أنت خارج النطاق الجغرافي المسموح به لهذا الفرع';
        }

        return null;
    }
}
