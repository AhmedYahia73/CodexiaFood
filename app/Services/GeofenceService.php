<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Http\Request;

class GeofenceService
{
    /**
     * Determine if a point (lat, lng) is inside a polygon using Ray-Casting algorithm.
     *
     * @param  array<int, mixed>  $polygon
     */
    public function isPointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $points = $this->normalizePolygonPoints($polygon);

        $numPoints = count($points);
        if ($numPoints < 3) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $numPoints - 1; $i < $numPoints; $j = $i++) {
            $xi = $points[$i]['lat'];
            $yi = $points[$i]['lng'];
            $xj = $points[$j]['lat'];
            $yj = $points[$j]['lng'];

            // Check if horizontal ray crosses edge (xi, yi) -> (xj, yj)
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
     * Normalize polygon points to an array of ['lat' => float, 'lng' => float].
     *
     * @return array<int, array{lat: float, lng: float}>
     */
    public function normalizePolygonPoints(mixed $polygon): array
    {
        while (is_string($polygon)) {
            $decoded = json_decode($polygon, true);
            if (! is_array($decoded)) {
                break;
            }
            $polygon = $decoded;
        }

        if (! is_array($polygon)) {
            return [];
        }

        $normalized = [];

        foreach ($polygon as $point) {
            if (! is_array($point)) {
                continue;
            }

            $lower = array_change_key_case($point, CASE_LOWER);

            $pLat = $lower['lat'] ?? $lower['latitude'] ?? null;
            $pLng = $lower['lng'] ?? $lower['long'] ?? $lower['lon'] ?? $lower['longitude'] ?? null;

            if ($pLat === null && isset($point[0])) {
                $pLat = $point[0];
            }
            if ($pLng === null && isset($point[1])) {
                $pLng = $point[1];
            }

            if ($pLat !== null && $pLng !== null && is_numeric($pLat) && is_numeric($pLng)) {
                $normalized[] = [
                    'lat' => (float) $pLat,
                    'lng' => (float) $pLng,
                ];
            }
        }

        return $normalized;
    }

    /**
     * Extract user coordinates from request query, body, or headers.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function getUserCoordinates(Request $request): ?array
    {
        $all = array_change_key_case($request->all(), CASE_LOWER);

        $lat = $all['lat']
            ?? $all['latitude']
            ?? $request->input('location.lat')
            ?? $request->input('location.Lat')
            ?? $request->input('location.latitude')
            ?? $request->input('coords.latitude')
            ?? $request->input('coords.lat')
            ?? $request->header('X-Lat')
            ?? $request->header('X-Latitude')
            ?? $request->header('lat')
            ?? $request->header('latitude');

        $lng = $all['lng']
            ?? $all['long']
            ?? $all['lon']
            ?? $all['longitude']
            ?? $request->input('location.lng')
            ?? $request->input('location.Lng')
            ?? $request->input('location.long')
            ?? $request->input('location.Long')
            ?? $request->input('location.lon')
            ?? $request->input('location.longitude')
            ?? $request->input('coords.longitude')
            ?? $request->input('coords.long')
            ?? $request->input('coords.lng')
            ?? $request->header('X-Lng')
            ?? $request->header('X-Longitude')
            ?? $request->header('X-Long')
            ?? $request->header('lng')
            ?? $request->header('longitude')
            ?? $request->header('long');

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
            return 'هذه الطاولة غير مرتبطة بفرع محدد';
        }

        $points = $this->normalizePolygonPoints($branch->location);
        if (count($points) < 3) {
            return 'لم يتم تحديد النطاق الجغرافي لهذا الفرع';
        }

        $coords = $this->getUserCoordinates($request);
        if ($coords === null) {
            return 'يرجى تحديد الموقع الجغرافي الخاص بك (lat, lng)';
        }

        if (! $this->isPointInPolygon($coords['lat'], $coords['lng'], $points)) {
            return 'أنت خارج النطاق الجغرافي المسموح به لهذا الفرع';
        }

        return null;
    }
}
