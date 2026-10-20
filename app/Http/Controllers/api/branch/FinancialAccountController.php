<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\FinancialAccountResource;
use App\Models\Branch;
use App\Models\FinancialAccount;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FinancialAccountController extends BaseBranchController
{
    use image;

    public function selectOptions(): JsonResponse
    {
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1',
        ]);

        $branchId = $this->getBranchId();
        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        $accounts = FinancialAccount::where('branch_id', $branchId)
            ->with('branch')
            ->latest()
            ->paginate($perPage);

        return FinancialAccountResource::collection($accounts)->additional([
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'icon' => $request->hasFile('icon')
                ? 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:4096'
                : 'nullable|string|max:500',
            'balance' => 'nullable|numeric',
            'status' => 'nullable|boolean',
            'is_cash' => 'sometimes|nullable|boolean',
            'branch_id' => 'sometimes|nullable|integer',
        ]);

        $iconPath = $this->upload($request, 'icon', 'financial_accounts');
        if (! $iconPath && $request->filled('icon') && is_string($request->input('icon'))) {
            $inputIcon = $request->input('icon');
            if (! str_starts_with($inputIcon, 'http://') && ! str_starts_with($inputIcon, 'https://')) {
                $iconPath = $inputIcon;
            }
        }

        $account = FinancialAccount::create([
            'name' => $validated['name'],
            'icon' => $iconPath,
            'balance' => $validated['balance'] ?? 0,
            'status' => $validated['status'] ?? true,
            'is_cash' => (bool) ($validated['is_cash'] ?? false),
            'branch_id' => $branchId,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Financial account created successfully.',
            'data' => new FinancialAccountResource($account->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(FinancialAccount $financialAccount): JsonResponse
    {
        $this->ensureBelongsToBranch($financialAccount);
        $branchId = $this->getBranchId();

        return response()->json([
            'status' => true,
            'data' => new FinancialAccountResource($financialAccount->load('branch')),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        $this->ensureBelongsToBranch($financialAccount);
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'icon' => $request->hasFile('icon')
                ? 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:4096'
                : 'nullable|string|max:500',
            'balance' => 'nullable|numeric',
            'status' => 'nullable|boolean',
            'is_cash' => 'sometimes|nullable|boolean',
            'branch_id' => 'sometimes|nullable|integer',
        ]);

        $dataToUpdate = array_filter([
            'name' => $validated['name'] ?? null,
            'balance' => $validated['balance'] ?? null,
            'status' => $request->has('status') ? $validated['status'] : null,
            'is_cash' => $request->has('is_cash') ? (bool) $validated['is_cash'] : null,
        ], fn ($val) => ! is_null($val));

        if ($request->hasFile('icon')) {
            if ($financialAccount->icon) {
                $this->deleteImage($financialAccount->icon);
            }
            $dataToUpdate['icon'] = $this->upload($request, 'icon', 'financial_accounts');
        } elseif ($request->filled('icon') && is_string($request->input('icon'))) {
            $inputIcon = $request->input('icon');
            if (! str_starts_with($inputIcon, 'http://') && ! str_starts_with($inputIcon, 'https://')) {
                $dataToUpdate['icon'] = $inputIcon;
            }
        }

        $dataToUpdate['branch_id'] = $branchId;
        $financialAccount->update($dataToUpdate);

        return response()->json([
            'status' => true,
            'message' => 'Financial account updated successfully.',
            'data' => new FinancialAccountResource($financialAccount->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::where('id', $branchId)->select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(FinancialAccount $financialAccount): JsonResponse
    {
        $this->ensureBelongsToBranch($financialAccount);

        if ($financialAccount->icon) {
            $this->deleteImage($financialAccount->icon);
        }

        $financialAccount->delete();

        return response()->json([
            'status' => true,
            'message' => 'Financial account deleted successfully.',
        ]);
    }
}
