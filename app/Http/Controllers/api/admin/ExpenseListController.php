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
        $expenseLists = ExpenseList::latest()->paginate($request->get('per_page', 15));

        return ExpenseListResource::collection($expenseLists);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $expenseList = ExpenseList::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Expense list created successfully.',
            'data' => new ExpenseListResource($expenseList),
        ], 201);
    }

    public function show(ExpenseList $expenseList): ExpenseListResource
    {
        return new ExpenseListResource($expenseList);
    }

    public function update(Request $request, ExpenseList $expenseList): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $expenseList->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Expense list updated successfully.',
            'data' => new ExpenseListResource($expenseList->fresh()),
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
