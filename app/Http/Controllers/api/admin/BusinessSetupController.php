<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessSetupResource;
use App\Models\BusinessSetup;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class BusinessSetupController extends Controller
{
    use image;

    /**
     * Display the first business setup record.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

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

        if (is_string($request->input('menu'))) {
            $decoded = json_decode($request->input('menu'), true);
            if (is_array($decoded) || is_null($decoded)) {
                $request->merge(['menu' => $decoded]);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'face' => 'required|string|max:255',
            'instagram' => 'required|string|max:255',
            'whats' => 'required|string|max:255',
            'description' => 'required|string',
            'branch_cover' => 'nullable|numeric|min:0',
            'start_day' => ['nullable', 'regex:/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'end_day' => ['nullable', 'regex:/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'logo' => $request->hasFile('logo')
                ? 'required|image|mimes:jpeg,png,jpg,gif,webp,svg|max:4096'
                : 'required|string|max:255',
            'table_order_url' => 'sometimes|nullable|string|max:255',

            /**
             * Whether gift promotion is enabled.
             *
             * @var bool|null
             *
             * @example false
             */
            'has_gift' => 'sometimes|nullable|boolean',

            /**
             * Gift item price.
             *
             * @var int|null
             *
             * @example 1
             */
            'price_gift' => 'sometimes|nullable|integer|min:0',

            /**
             * Menu images (array of uploaded image files or existing image paths/URLs).
             *
             * @var array<string>|null
             *
             * @example ["business_setup/menu1.png"]
             */
            'menu' => 'sometimes|nullable|array',
            'menu.*' => 'nullable',
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
            'branch_cover' => $validated['branch_cover'] ?? ($setup?->branch_cover ?? 5.00),
            'table_order_url' => $validated['table_order_url'] ?? ($setup?->table_order_url ?? null),
            'has_gift' => array_key_exists('has_gift', $validated) ? (bool) $validated['has_gift'] : ($setup?->has_gift ?? false),
            'price_gift' => array_key_exists('price_gift', $validated) && ! is_null($validated['price_gift']) ? (int) $validated['price_gift'] : ($setup?->price_gift ?? 1),
        ];

        if (array_key_exists('start_day', $validated)) {
            $data['start_day'] = $validated['start_day'] ? (strlen($validated['start_day']) === 5 ? $validated['start_day'].':00' : $validated['start_day']) : null;
        }
        if (array_key_exists('end_day', $validated)) {
            $data['end_day'] = $validated['end_day'] ? (strlen($validated['end_day']) === 5 ? $validated['end_day'].':00' : $validated['end_day']) : null;
        }

        if ($logoPath !== null) {
            $data['logo'] = $logoPath;
        }

        if (array_key_exists('menu', $validated) || $request->hasFile('menu')) {
            $rawMenu = $request->all()['menu'] ?? null;

            if (is_null($rawMenu)) {
                $data['menu'] = null;
                if ($setup && is_array($setup->menu)) {
                    foreach ($setup->menu as $oldImage) {
                        $oldPath = str_contains($oldImage, '/storage/')
                            ? substr($oldImage, strpos($oldImage, '/storage/') + strlen('/storage/'))
                            : $oldImage;
                        $this->deleteImage($oldPath);
                    }
                }
            } else {
                $processedMenu = [];
                $menuItems = is_array($rawMenu) ? $rawMenu : [$rawMenu];

                foreach ($menuItems as $key => $item) {
                    if ($item instanceof UploadedFile) {
                        $processedMenu[] = $item->store('business_setup', 'public');
                    } elseif ($request->hasFile("menu.{$key}")) {
                        $processedMenu[] = $request->file("menu.{$key}")->store('business_setup', 'public');
                    } elseif (is_string($item) && trim($item) !== '') {
                        if (str_contains($item, '/storage/')) {
                            $processedMenu[] = substr($item, strpos($item, '/storage/') + strlen('/storage/'));
                        } else {
                            $processedMenu[] = $item;
                        }
                    }
                }

                $data['menu'] = $processedMenu;

                if ($setup && is_array($setup->menu)) {
                    $removedImages = array_diff($setup->menu, $processedMenu);
                    foreach ($removedImages as $removed) {
                        $removedPath = str_contains($removed, '/storage/')
                            ? substr($removed, strpos($removed, '/storage/') + strlen('/storage/'))
                            : $removed;
                        $this->deleteImage($removedPath);
                    }
                }
            }
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
