<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseListResource;
use App\Models\ExpenseList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseListController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $expenseLists = ExpenseList::with('financialAccount')->latest()->paginate($request->get('per_page', 15));

        return ExpenseListResource::collection($expenseLists);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|array:en,ar',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'required|string|max:255',
            'description' => 'nullable|array:en,ar',
            'description.en' => 'nullable|string',
            'description.ar' => 'nullable|string',
            'financial_id' => 'sometimes|nullable|integer|exists:financial_accounts,id',
            'financial_account_id' => 'sometimes|nullable|integer|exists:financial_accounts,id',
        ]);

        if (isset($validated['financial_account_id']) && ! isset($validated['financial_id'])) {
            $validated['financial_id'] = $validated['financial_account_id'];
        }
        unset($validated['financial_account_id']);

        $expenseList = ExpenseList::create($validated);
        $expenseList->load('financialAccount');

        return response()->json([
            'status' => true,
            'message' => 'Expense list created successfully.',
            'data' => new ExpenseListResource($expenseList),
        ], 201);
    }

    public function show(ExpenseList $expenseList): ExpenseListResource
    {
        $expenseList->load('financialAccount');

        return new ExpenseListResource($expenseList);
    }

    public function update(Request $request, ExpenseList $expenseList): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|array:en,ar',
            'name.en' => 'required_with:name|string|max:255',
            'name.ar' => 'required_with:name|string|max:255',
            'description' => 'nullable|array:en,ar',
            'description.en' => 'nullable|string',
            'description.ar' => 'nullable|string',
            'financial_id' => 'sometimes|nullable|integer|exists:financial_accounts,id',
            'financial_account_id' => 'sometimes|nullable|integer|exists:financial_accounts,id',
        ]);

        if (array_key_exists('financial_account_id', $validated) && ! array_key_exists('financial_id', $validated)) {
            $validated['financial_id'] = $validated['financial_account_id'];
        }
        unset($validated['financial_account_id']);

        $expenseList->update($validated);
        $expenseList->load('financialAccount');

        return response()->json([
            'status' => true,
            'message' => 'Expense list updated successfully.',
            'data' => new ExpenseListResource($expenseList->fresh()->load('financialAccount')),
        ]);
    }

    public function destroy(ExpenseList $expenseList): JsonResponse
    {
        $expenseList->delete();

        return response()->json([
            'status' => true,
            'message' => 'Expense list deleted successfully.',
        ]);
    }
}
