<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseDueResource;
use App\Models\PurchaseDue;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PurchaseDueController extends Controller
{
    /**
     * Display a listing of purchase dues with optional filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            /**
             * Filter by payment status ('all', 'overdue', 'unpaid', 'paid').
             *
             * @var string|null
             *
             * @example "unpaid"
             */
            'status' => 'sometimes|nullable|string|in:all,overdue,unpaid,paid',

            /**
             * Filter by specific purchase ID.
             *
             * @var int|null
             */
            'purchase_id' => 'sometimes|nullable|integer|exists:purchases,id',

            /**
             * Filter by specific supplier ID.
             *
             * @var int|null
             */
            'supplier_id' => 'sometimes|nullable|integer|exists:suppliers,id',

            /**
             * Filter by branch ID.
             *
             * @var int|null
             */
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',

            /**
             * Filter by exact due date (YYYY-MM-DD).
             *
             * @var string|null
             *
             * @example "2026-11-01"
             */
            'date' => 'sometimes|nullable|date',

            /**
             * Filter from due date (YYYY-MM-DD).
             *
             * @var string|null
             */
            'from_date' => 'sometimes|nullable|date',

            /**
             * Filter to due date (YYYY-MM-DD).
             *
             * @var string|null
             */
            'to_date' => 'sometimes|nullable|date',

            /**
             * Current page number.
             *
             * @var int|null
             */
            'page' => 'sometimes|nullable|integer|min:1',

            /**
             * Items per page.
             *
             * @var int|null
             */
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);

        $query = PurchaseDue::with(['purchase.supplier', 'purchase.branch'])
            ->when(! empty($validated['purchase_id']), fn ($q) => $q->where('purchase_id', $validated['purchase_id']))
            ->when(! empty($validated['supplier_id']), function ($q) use ($validated) {
                $q->whereHas('purchase', fn ($pq) => $pq->where('supplier_id', $validated['supplier_id']));
            })
            ->when(! empty($validated['branch_id']), function ($q) use ($validated) {
                $q->whereHas('purchase', fn ($pq) => $pq->where('branch_id', $validated['branch_id']));
            })
            ->when(! empty($validated['date']), fn ($q) => $q->whereDate('date', $validated['date']))
            ->when(! empty($validated['from_date']), fn ($q) => $q->whereDate('date', '>=', $validated['from_date']))
            ->when(! empty($validated['to_date']), fn ($q) => $q->whereDate('date', '<=', $validated['to_date']));

        $status = $validated['status'] ?? null;
        if ($status === 'overdue') {
            $query->whereColumn('payment', '<', 'amount')->whereDate('date', '<', today());
        } elseif ($status === 'unpaid') {
            $query->whereColumn('payment', '<', 'amount');
        } elseif ($status === 'paid') {
            $query->whereColumn('payment', '>=', 'amount');
        }

        $dues = $query->orderBy('date', 'asc')->paginate($validated['per_page'] ?? 15);

        return PurchaseDueResource::collection($dues);
    }

    /**
     * Display overdue purchase dues (payment < amount and date < today).
     */
    public function overdue(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'purchase_id' => 'sometimes|nullable|integer|exists:purchases,id',
            'supplier_id' => 'sometimes|nullable|integer|exists:suppliers,id',
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);

        $dues = PurchaseDue::with(['purchase.supplier', 'purchase.branch'])
            ->whereColumn('payment', '<', 'amount')
            ->whereDate('date', '<', today())
            ->when(! empty($validated['purchase_id']), fn ($q) => $q->where('purchase_id', $validated['purchase_id']))
            ->when(! empty($validated['supplier_id']), function ($q) use ($validated) {
                $q->whereHas('purchase', fn ($pq) => $pq->where('supplier_id', $validated['supplier_id']));
            })
            ->when(! empty($validated['branch_id']), function ($q) use ($validated) {
                $q->whereHas('purchase', fn ($pq) => $pq->where('branch_id', $validated['branch_id']));
            })
            ->orderBy('date', 'asc')
            ->paginate($validated['per_page'] ?? 15);

        return PurchaseDueResource::collection($dues);
    }

    /**
     * Display all unpaid or partially paid purchase dues (payment < amount).
     */
    public function unpaid(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'purchase_id' => 'sometimes|nullable|integer|exists:purchases,id',
            'supplier_id' => 'sometimes|nullable|integer|exists:suppliers,id',
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);

        $dues = PurchaseDue::with(['purchase.supplier', 'purchase.branch'])
            ->whereColumn('payment', '<', 'amount')
            ->when(! empty($validated['purchase_id']), fn ($q) => $q->where('purchase_id', $validated['purchase_id']))
            ->when(! empty($validated['supplier_id']), function ($q) use ($validated) {
                $q->whereHas('purchase', fn ($pq) => $pq->where('supplier_id', $validated['supplier_id']));
            })
            ->when(! empty($validated['branch_id']), function ($q) use ($validated) {
                $q->whereHas('purchase', fn ($pq) => $pq->where('branch_id', $validated['branch_id']));
            })
            ->orderBy('date', 'asc')
            ->paginate($validated['per_page'] ?? 15);

        return PurchaseDueResource::collection($dues);
    }

    /**
     * Display fully paid purchase dues (payment >= amount).
     */
    public function paid(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'purchase_id' => 'sometimes|nullable|integer|exists:purchases,id',
            'supplier_id' => 'sometimes|nullable|integer|exists:suppliers,id',
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);

        $dues = PurchaseDue::with(['purchase.supplier', 'purchase.branch'])
            ->whereColumn('payment', '>=', 'amount')
            ->when(! empty($validated['purchase_id']), fn ($q) => $q->where('purchase_id', $validated['purchase_id']))
            ->when(! empty($validated['supplier_id']), function ($q) use ($validated) {
                $q->whereHas('purchase', fn ($pq) => $pq->where('supplier_id', $validated['supplier_id']));
            })
            ->when(! empty($validated['branch_id']), function ($q) use ($validated) {
                $q->whereHas('purchase', fn ($pq) => $pq->where('branch_id', $validated['branch_id']));
            })
            ->orderBy('date', 'desc')
            ->paginate($validated['per_page'] ?? 15);

        return PurchaseDueResource::collection($dues);
    }

    /**
     * Show details of a single purchase due.
     */
    public function show(PurchaseDue $purchaseDue): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new PurchaseDueResource($purchaseDue->load(['purchase.supplier', 'purchase.branch'])),
        ]);
    }

    /**
     * Pay an installment for a purchase due.
     */
    public function pay(Request $request, PurchaseDue $purchaseDue): JsonResponse
    {
        $remaining = (float) $purchaseDue->remaining;

        if ($remaining <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'هذا القسط مدفوع بالكامل بالفعل ولا توجد مبالغ متبقية عليه.',
            ], 422);
        }

        // Support both 'payment' and 'amount' in input
        if (! $request->has('payment') && $request->has('amount')) {
            $request->merge(['payment' => $request->input('amount')]);
        }

        $validated = $request->validate([
            /**
             * Payment amount to pay for this due.
             *
             * @var float
             *
             * @example 500.00
             */
            'payment' => [
                'required',
                'numeric',
                'min:0.01',
                'max:'.$remaining,
            ],

            /**
             * Optional notes for this payment.
             *
             * @var string|null
             */
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        $amountPaid = round((float) $validated['payment'], 2);

        $result = DB::transaction(function () use ($purchaseDue, $amountPaid, $validated) {
            // 1. Update purchase due payment
            $purchaseDue->increment('payment', $amountPaid);

            if (! empty($validated['notes'])) {
                $existingNotes = $purchaseDue->notes ? $purchaseDue->notes."\n" : '';
                $purchaseDue->update([
                    'notes' => $existingNotes.'سداد: '.$amountPaid.' في '.now()->toDateTimeString().' - '.$validated['notes'],
                ]);
            }

            // 2. Update parent Purchase
            $purchase = $purchaseDue->purchase;
            if ($purchase) {
                $purchase->increment('paid_amount', $amountPaid);
                $purchase->decrement('remaining_amount', $amountPaid);

                // 3. Decrement Supplier Balance
                if ($purchase->supplier_id) {
                    Supplier::where('id', $purchase->supplier_id)->decrement('balance', $amountPaid);
                }
            }

            return $purchaseDue->fresh(['purchase.supplier', 'purchase.branch']);
        });

        $supplier = $result->purchase?->supplier;

        return response()->json([
            'status' => true,
            'message' => 'تم تسجيل سداد القسط بنجاح وخصم المبلغ من رصيد المورد.',
            'data' => new PurchaseDueResource($result),
            'meta' => [
                'paid_now' => $amountPaid,
                'due_remaining' => (float) $result->remaining,
                'due_status' => $result->status,
                'purchase_remaining' => (float) ($result->purchase?->remaining_amount ?? 0),
                'supplier_balance' => $supplier ? (float) $supplier->balance : null,
            ],
        ]);
    }
}
