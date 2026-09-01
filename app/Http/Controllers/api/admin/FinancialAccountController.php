<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FinancialAccountResource;
use App\Models\Branch;
use App\Models\FinancialAccount;
use App\trait\image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FinancialAccountController extends Controller
{
    use image;

    public function selectOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = FinancialAccount::with('branch')->latest()->paginate($request->get('per_page', 15));

        return FinancialAccountResource::collection($accounts)->additional([
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'balance' => 'nullable|numeric',
            'status' => 'nullable|boolean',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $iconPath = $this->upload($request, 'icon', 'financial_accounts');

        $account = FinancialAccount::create([
            'name' => $validated['name'],
            'icon' => $iconPath,
            'balance' => $validated['balance'] ?? 0,
            'status' => $validated['status'] ?? true,
            'branch_id' => $validated['branch_id'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Financial account created successfully.',
            'data' => new FinancialAccountResource($account->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ], 201);
    }

    public function show(FinancialAccount $financialAccount): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new FinancialAccountResource($financialAccount->load('branch')),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function update(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'balance' => 'nullable|numeric',
            'status' => 'nullable|boolean',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $dataToUpdate = array_filter([
            'name' => $validated['name'] ?? null,
            'balance' => $validated['balance'] ?? null,
            'status' => $request->has('status') ? $validated['status'] : null,
            'branch_id' => $request->has('branch_id') ? $validated['branch_id'] : null,
        ], fn ($val) => ! is_null($val));

        if ($request->hasFile('icon')) {
            $newIconPath = $this->update_image($request, $financialAccount->icon, 'icon', 'financial_accounts');
            if ($newIconPath) {
                $dataToUpdate['icon'] = $newIconPath;
            }
        }

        $financialAccount->update($dataToUpdate);

        return response()->json([
            'status' => true,
            'message' => 'Financial account updated successfully.',
            'data' => new FinancialAccountResource($financialAccount->fresh(['branch'])),
            'select_options' => [
                'branches' => Branch::select('id', 'name')->get(),
            ],
        ]);
    }

    public function destroy(FinancialAccount $financialAccount): JsonResponse
    {
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
