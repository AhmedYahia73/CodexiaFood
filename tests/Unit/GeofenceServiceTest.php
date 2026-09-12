<?php

use App\Models\Branch;
use App\Services\GeofenceService;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->geofence = new GeofenceService;
});

test('isPointInPolygon correctly identifies inside and outside points', function () {
    $polygon = [
        ['lat' => 10.0, 'lng' => 10.0],
        ['lat' => 10.0, 'lng' => 20.0],
        ['lat' => 20.0, 'lng' => 20.0],
        ['lat' => 20.0, 'lng' => 10.0],
    ];

    expect($this->geofence->isPointInPolygon(15.0, 15.0, $polygon))->toBeTrue();
    expect($this->geofence->isPointInPolygon(5.0, 15.0, $polygon))->toBeFalse();
    expect($this->geofence->isPointInPolygon(25.0, 15.0, $polygon))->toBeFalse();
    expect($this->geofence->isPointInPolygon(15.0, 5.0, $polygon))->toBeFalse();
    expect($this->geofence->isPointInPolygon(15.0, 25.0, $polygon))->toBeFalse();
});

test('isPointInPolygon returns false if polygon has fewer than 3 points', function () {
    expect($this->geofence->isPointInPolygon(10.0, 10.0, []))->toBeFalse();
    expect($this->geofence->isPointInPolygon(10.0, 10.0, [['lat' => 10.0, 'lng' => 10.0]]))->toBeFalse();
    expect($this->geofence->isPointInPolygon(10.0, 10.0, [
        ['lat' => 10.0, 'lng' => 10.0],
        ['lat' => 20.0, 'lng' => 20.0],
    ]))->toBeFalse();
});

test('getUserCoordinates extracts from query, body, or headers with flexible aliases', function () {
    // From body/query standard
    $req1 = Request::create('/test', 'POST', ['lat' => 30.5, 'lng' => 31.5]);
    expect($this->geofence->getUserCoordinates($req1))->toBe(['lat' => 30.5, 'lng' => 31.5]);

    // From alternative names latitude/longitude
    $req2 = Request::create('/test', 'GET', ['latitude' => '29.1', 'longitude' => '30.2']);
    expect($this->geofence->getUserCoordinates($req2))->toBe(['lat' => 29.1, 'lng' => 30.2]);

    // From lat/long
    $reqLong = Request::create('/test', 'POST', ['lat' => '30.1', 'long' => '31.2']);
    expect($this->geofence->getUserCoordinates($reqLong))->toBe(['lat' => 30.1, 'lng' => 31.2]);

    // From nested location object
    $reqNested = Request::create('/test', 'POST', ['location' => ['lat' => 30.15, 'lng' => 31.25]]);
    expect($this->geofence->getUserCoordinates($reqNested))->toBe(['lat' => 30.15, 'lng' => 31.25]);

    // From headers X-Lat / X-Lng
    $req3 = Request::create('/test', 'GET');
    $req3->headers->set('X-Lat', '33.33');
    $req3->headers->set('X-Lng', '34.44');
    expect($this->geofence->getUserCoordinates($req3))->toBe(['lat' => 33.33, 'lng' => 34.44]);

    // Missing
    $req4 = Request::create('/test', 'GET');
    expect($this->geofence->getUserCoordinates($req4))->toBeNull();

    // Invalid non-numeric
    $req5 = Request::create('/test', 'GET', ['lat' => 'invalid', 'lng' => '30.0']);
    expect($this->geofence->getUserCoordinates($req5))->toBeNull();
});

test('validateLocation returns error when branch is null or unconfigured', function () {
    $request = Request::create('/test', 'GET', ['lat' => 30.0, 'lng' => 31.0]);

    // Null branch
    expect($this->geofence->validateLocation($request, null))
        ->toBe('هذه الطاولة غير مرتبطة بفرع محدد');

    // Branch with null location
    $branch = new Branch(['location' => null]);
    expect($this->geofence->validateLocation($request, $branch))
        ->toBe('لم يتم تحديد النطاق الجغرافي لهذا الفرع');

    // Branch with empty location
    $branchWithEmpty = new Branch(['location' => []]);
    expect($this->geofence->validateLocation($request, $branchWithEmpty))
        ->toBe('لم يتم تحديد النطاق الجغرافي لهذا الفرع');

    // Branch with fewer than 3 points
    $branchFew = new Branch(['location' => [['lat' => 30.0, 'lng' => 31.0]]]);
    expect($this->geofence->validateLocation($request, $branchFew))
        ->toBe('لم يتم تحديد النطاق الجغرافي لهذا الفرع');
});

test('validateLocation requires coordinates and checks perimeter', function () {
    $branch = new Branch([
        'location' => [
            ['lat' => 30.0, 'lng' => 31.0],
            ['lat' => 30.0, 'lng' => 32.0],
            ['lat' => 31.0, 'lng' => 32.0],
            ['lat' => 31.0, 'lng' => 31.0],
        ],
    ]);

    // Missing coordinates
    $reqNoCoords = Request::create('/test', 'GET');
    expect($this->geofence->validateLocation($reqNoCoords, $branch))
        ->toBe('يرجى تحديد الموقع الجغرافي الخاص بك (lat, lng)');

    // Outside coordinates
    $reqOutside = Request::create('/test', 'GET', ['lat' => 50.0, 'lng' => 10.0]);
    expect($this->geofence->validateLocation($reqOutside, $branch))
        ->toBe('أنت خارج النطاق الجغرافي المسموح به لهذا الفرع');

    // Inside coordinates
    $reqInside = Request::create('/test', 'GET', ['lat' => 30.5, 'lng' => 31.5]);
    expect($this->geofence->validateLocation($reqInside, $branch))->toBeNull();
});

test('normalizePolygonPoints handles json string and flexible point keys', function () {
    $json = json_encode([
        ['LAT' => '30.1', 'LONG' => '31.1'],
        ['latitude' => 30.2, 'longitude' => 31.2],
        ['lat' => 30.3, 'lng' => 31.3],
    ]);

    $normalized = $this->geofence->normalizePolygonPoints($json);
    expect($normalized)->toHaveCount(3);
    expect($normalized[0])->toBe(['lat' => 30.1, 'lng' => 31.1]);
    expect($normalized[1])->toBe(['lat' => 30.2, 'lng' => 31.2]);
    expect($normalized[2])->toBe(['lat' => 30.3, 'lng' => 31.3]);
});

test('user sample polygon test points inside and outside', function () {
    $polygon = [
        ['lat' => 34.087037887904366, 'lng' => 31.51500799509473],
        ['lat' => 33.649174465794, 'lng' => 22.55016424509473],
        ['lat' => 25.71853395873908, 'lng' => 20.79235174509473],
        ['lat' => 18.72718813381065, 'lng' => 27.47203924509473],
        ['lat' => 19.225869787807213, 'lng' => 42.589226745094734],
    ];

    // Inside
    expect($this->geofence->isPointInPolygon(26.0, 28.0, $polygon))->toBeTrue();

    // Outside to the North
    expect($this->geofence->isPointInPolygon(40.0, 30.0, $polygon))->toBeFalse();

    // Outside to the South
    expect($this->geofence->isPointInPolygon(10.0, 30.0, $polygon))->toBeFalse();

    // Outside to the West
    expect($this->geofence->isPointInPolygon(25.0, 10.0, $polygon))->toBeFalse();

    // Outside to the East
    expect($this->geofence->isPointInPolygon(25.0, 50.0, $polygon))->toBeFalse();

    // Point at lat=30.0, lng=40.0 (outside the east boundary between V0 and V4)
    expect($this->geofence->isPointInPolygon(30.0, 40.0, $polygon))->toBeFalse();
});
