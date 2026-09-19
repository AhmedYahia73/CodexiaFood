<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessSetupResource;
use App\Models\BusinessSetup;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessSetupController extends Controller
{
    use image;

    /**
     * Display the first business setup record.
     */
    public function index(): JsonResponse
    {
        $businessSetup = BusinessSetup::first();

        return response()->json([
            'status' => true,
            'data' => $businessSetup ? new BusinessSetupResource($businessSetup) : null,
        ]);
    }

    /**
     * Create the business setup if not exists, or update if it exists.
     */
    public function update(Request $request, ?BusinessSetup $businessSetup = null): JsonResponse
    {
        $setup = ($businessSetup && $businessSetup->exists) ? $businessSetup : BusinessSetup::first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'face' => 'required|string|max:255',
            'instagram' => 'required|string|max:255',
            'whats' => 'required|string|max:255',
            'description' => 'required|string',
            'logo' => $request->hasFile('logo')
                ? 'required|image|mimes:jpeg,png,jpg,gif,webp,svg|max:4096'
                : 'required|string|max:255',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            if ($setup && $setup->logo) {
                $logoPath = $this->update_image($request, $setup->logo, 'logo', 'business_setup');
            } else {
                $logoPath = $this->upload($request, 'logo', 'business_setup');
            }
        } elseif ($request->filled('logo') && is_string($request->input('logo'))) {
            $logoInput = $request->input('logo');
            if (str_contains($logoInput, '/storage/')) {
                $logoPath = substr($logoInput, strpos($logoInput, '/storage/') + strlen('/storage/'));
            } else {
                $logoPath = $logoInput;
            }
        }

        $data = [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'face' => $validated['face'],
            'instagram' => $validated['instagram'],
            'whats' => $validated['whats'],
            'description' => $validated['description'],
        ];

        if ($logoPath !== null) {
            $data['logo'] = $logoPath;
        }

        $isNew = ! $setup;

        if ($isNew) {
            $setup = BusinessSetup::create($data);
        } else {
            $setup->update($data);
        }

        return response()->json([
            'status' => true,
            'message' => $isNew ? 'Business setup created successfully.' : 'Business setup updated successfully.',
            'data' => new BusinessSetupResource($setup->fresh()),
        ]);
    }
}
