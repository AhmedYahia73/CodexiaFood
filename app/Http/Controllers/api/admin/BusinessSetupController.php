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

        foreach (['deleted_menu', 'delete_menu'] as $delKey) {
            if ($request->has($delKey) && is_string($request->input($delKey))) {
                $rawVal = $request->input($delKey);
                $decoded = json_decode($rawVal, true);
                if (is_array($decoded)) {
                    $request->merge([$delKey => $decoded]);
                } elseif (trim($rawVal) !== '') {
                    $request->merge([$delKey => [$rawVal]]);
                }
            }
        }

        foreach (['menu', 'new_menu'] as $menuKey) {
            if (is_string($request->input($menuKey))) {
                $decoded = json_decode($request->input($menuKey), true);
                if (is_array($decoded) || is_null($decoded)) {
                    $request->merge([$menuKey => $decoded]);
                }
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

            /**
             * New menu images to append to current menu.
             *
             * @var array<string>|null
             */
            'new_menu' => 'sometimes|nullable|array',
            'new_menu.*' => 'nullable',

            /**
             * Array of menu image paths, URLs, or indices to delete from the menu.
             *
             * @var array<string>|null
             *
             * @example ["business_setup/menu1.png"]
             */
            'deleted_menu' => 'sometimes|nullable|array',
            'deleted_menu.*' => 'nullable',

            /**
             * Alias for deleted_menu.
             *
             * @var array<string>|null
             *
             * @example ["business_setup/menu1.png"]
             */
            'delete_menu' => 'sometimes|nullable|array',
            'delete_menu.*' => 'nullable',
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

        $currentMenu = ($setup && is_array($setup->menu)) ? $setup->menu : [];
        $menuModified = false;

        // 1. Process deletions via deleted_menu / delete_menu
        $deletedMenuInput = $request->input('deleted_menu') ?? $request->input('delete_menu');
        if (! empty($deletedMenuInput)) {
            $deletedItems = is_array($deletedMenuInput) ? $deletedMenuInput : [$deletedMenuInput];

            foreach ($deletedItems as $del) {
                if (is_null($del) || $del === '') {
                    continue;
                }

                $delPath = (string) $del;
                if (str_contains($delPath, '/storage/')) {
                    $delPath = substr($delPath, strpos($delPath, '/storage/') + strlen('/storage/'));
                }
                $delPath = ltrim($delPath, '/');

                foreach ($currentMenu as $idx => $existingItem) {
                    $normExisting = str_contains($existingItem, '/storage/')
                        ? substr($existingItem, strpos($existingItem, '/storage/') + strlen('/storage/'))
                        : $existingItem;
                    $normExisting = ltrim($normExisting, '/');

                    if ($normExisting === $delPath || (string) $idx === (string) $del) {
                        $this->deleteImage($normExisting);
                        unset($currentMenu[$idx]);
                        $menuModified = true;
                    }
                }
            }
            $currentMenu = array_values($currentMenu);
        }

        // 2. Process menu additions or replacements
        $hasMenuKey = array_key_exists('menu', $request->all()) || $request->hasFile('menu');

        if ($hasMenuKey) {
            $rawMenu = $request->all()['menu'] ?? null;

            if (is_null($rawMenu) && ! $request->hasFile('menu')) {
                foreach ($currentMenu as $oldImg) {
                    $oldPath = str_contains($oldImg, '/storage/')
                        ? substr($oldImg, strpos($oldImg, '/storage/') + strlen('/storage/'))
                        : $oldImg;
                    $this->deleteImage(ltrim($oldPath, '/'));
                }
                $currentMenu = null;
                $menuModified = true;
            } elseif (is_array($rawMenu) && empty($rawMenu) && ! $request->hasFile('menu')) {
                foreach ($currentMenu as $oldImg) {
                    $oldPath = str_contains($oldImg, '/storage/')
                        ? substr($oldImg, strpos($oldImg, '/storage/') + strlen('/storage/'))
                        : $oldImg;
                    $this->deleteImage(ltrim($oldPath, '/'));
                }
                $currentMenu = [];
                $menuModified = true;
            } else {
                $menuItems = is_array($rawMenu) ? $rawMenu : ($rawMenu ? [$rawMenu] : []);
                $existingStringsInRequest = [];
                $newUploadedFiles = [];

                foreach ($menuItems as $key => $item) {
                    if ($item instanceof UploadedFile) {
                        $newUploadedFiles[] = $item->store('business_setup', 'public');
                    } elseif ($request->hasFile("menu.{$key}")) {
                        $newUploadedFiles[] = $request->file("menu.{$key}")->store('business_setup', 'public');
                    } elseif (is_string($item) && trim($item) !== '') {
                        $norm = str_contains($item, '/storage/')
                            ? substr($item, strpos($item, '/storage/') + strlen('/storage/'))
                            : $item;
                        $existingStringsInRequest[] = ltrim($norm, '/');
                    }
                }

                // If only files were uploaded, append them to current menu
                if (! empty($newUploadedFiles) && empty($existingStringsInRequest)) {
                    $currentMenu = array_merge($currentMenu, $newUploadedFiles);
                    $menuModified = true;
                }
                // If existing strings were passed in menu, use them + new files and delete omitted ones
                elseif (! empty($existingStringsInRequest)) {
                    foreach ($currentMenu as $oldImg) {
                        $oldNorm = str_contains($oldImg, '/storage/')
                            ? substr($oldImg, strpos($oldImg, '/storage/') + strlen('/storage/'))
                            : $oldImg;
                        $oldNorm = ltrim($oldNorm, '/');

                        if (! in_array($oldNorm, $existingStringsInRequest, true)) {
                            $this->deleteImage($oldNorm);
                        }
                    }
                    $currentMenu = array_merge($existingStringsInRequest, $newUploadedFiles);
                    $menuModified = true;
                }
            }
        }

        // 3. Process new_menu if provided explicitly as an append key
        if ($request->hasFile('new_menu') || $request->has('new_menu')) {
            $rawNewMenu = $request->all()['new_menu'] ?? [];
            $newMenuItems = is_array($rawNewMenu) ? $rawNewMenu : [$rawNewMenu];

            foreach ($newMenuItems as $key => $item) {
                if ($item instanceof UploadedFile) {
                    $currentMenu[] = $item->store('business_setup', 'public');
                    $menuModified = true;
                } elseif ($request->hasFile("new_menu.{$key}")) {
                    $currentMenu[] = $request->file("new_menu.{$key}")->store('business_setup', 'public');
                    $menuModified = true;
                }
            }
        }

        if ($menuModified) {
            $data['menu'] = is_array($currentMenu) ? array_values($currentMenu) : null;
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
