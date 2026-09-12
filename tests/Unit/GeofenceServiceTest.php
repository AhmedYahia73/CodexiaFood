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

test('isPointInPolygon allows all points if polygon has fewer than 3 points', function () {
    expect($this->geofence->isPointInPolygon(10.0, 10.0, []))->toBeTrue();
    expect($this->geofence->isPointInPolygon(10.0, 10.0, [['lat' => 10.0, 'lng' => 10.0]]))->toBeTrue();
});

test('getUserCoordinates extracts from query, body, or headers', function () {
    // From body/query
    $req1 = Request::create('/test', 'POST', ['lat' => 30.5, 'lng' => 31.5]);
    expect($this->geofence->getUserCoordinates($req1))->toBe(['lat' => 30.5, 'lng' => 31.5]);

    // From alternative names latitude/longitude
    $req2 = Request::create('/test', 'GET', ['latitude' => '29.1', 'longitude' => '30.2']);
    expect($this->geofence->getUserCoordinates($req2))->toBe(['lat' => 29.1, 'lng' => 30.2]);

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

test('validateLocation returns null when branch is null or has no location', function () {
    $request = Request::create('/test', 'GET');
    expect($this->geofence->validateLocation($request, null))->toBeNull();

    $branch = new Branch(['location' => null]);
    expect($this->geofence->validateLocation($request, $branch))->toBeNull();

    $branchWithEmpty = new Branch(['location' => []]);
    expect($this->geofence->validateLocation($request, $branchWithEmpty))->toBeNull();
});
