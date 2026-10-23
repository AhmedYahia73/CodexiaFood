<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleDueResource;
use App\Models\SaleDue;
use App\Models\Saler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SaleDueController extends Controller
{
    /**
     * Display a listing of sale dues with optional filters.
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
             * Filter by specific sale ID.
             *
             * @var int|null
             */
            'sale_id' => 'sometimes|nullable|integer|exists:sales,id',

            /**
             * Filter by specific saler/client ID.
             *
             * @var int|null
             */
            'saler_id' => 'sometimes|nullable|integer|exists:salers,id',

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

        $query = SaleDue::with(['sale.saler', 'sale.branch'])
            ->when(! empty($validated['sale_id']), fn ($q) => $q->where('sale_id', $validated['sale_id']))
            ->when(! empty($validated['saler_id']), function ($q) use ($validated) {
                $q->whereHas('sale', fn ($sq) => $sq->where('saler_id', $validated['saler_id']));
            })
            ->when(! empty($validated['branch_id']), function ($q) use ($validated) {
                $q->whereHas('sale', fn ($sq) => $sq->where('branch_id', $validated['branch_id']));
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

        return SaleDueResource::collection($dues);
    }

    /**
     * Display overdue sale dues (payment < amount and date < today).
     */
    public function overdue(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'sale_id' => 'sometimes|nullable|integer|exists:sales,id',
            'saler_id' => 'sometimes|nullable|integer|exists:salers,id',
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);

        $dues = SaleDue::with(['sale.saler', 'sale.branch'])
            ->whereColumn('payment', '<', 'amount')
            ->whereDate('date', '<', today())
            ->when(! empty($validated['sale_id']), fn ($q) => $q->where('sale_id', $validated['sale_id']))
            ->when(! empty($validated['saler_id']), function ($q) use ($validated) {
                $q->whereHas('sale', fn ($sq) => $sq->where('saler_id', $validated['saler_id']));
            })
            ->when(! empty($validated['branch_id']), function ($q) use ($validated) {
                $q->whereHas('sale', fn ($sq) => $sq->where('branch_id', $validated['branch_id']));
            })
            ->orderBy('date', 'asc')
            ->paginate($validated['per_page'] ?? 15);

        return SaleDueResource::collection($dues);
    }

    /**
     * Display all unpaid or partially paid sale dues (payment < amount).
     */
    public function unpaid(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'sale_id' => 'sometimes|nullable|integer|exists:sales,id',
            'saler_id' => 'sometimes|nullable|integer|exists:salers,id',
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);

        $dues = SaleDue::with(['sale.saler', 'sale.branch'])
            ->whereColumn('payment', '<', 'amount')
            ->when(! empty($validated['sale_id']), fn ($q) => $q->where('sale_id', $validated['sale_id']))
            ->when(! empty($validated['saler_id']), function ($q) use ($validated) {
                $q->whereHas('sale', fn ($sq) => $sq->where('saler_id', $validated['saler_id']));
            })
            ->when(! empty($validated['branch_id']), function ($q) use ($validated) {
                $q->whereHas('sale', fn ($sq) => $sq->where('branch_id', $validated['branch_id']));
            })
            ->orderBy('date', 'asc')
            ->paginate($validated['per_page'] ?? 15);

        return SaleDueResource::collection($dues);
    }

    /**
     * Display fully paid sale dues (payment >= amount).
     */
    public function paid(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'sale_id' => 'sometimes|nullable|integer|exists:sales,id',
            'saler_id' => 'sometimes|nullable|integer|exists:salers,id',
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
            'page' => 'sometimes|nullable|integer|min:1',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);

        $dues = SaleDue::with(['sale.saler', 'sale.branch'])
            ->whereColumn('payment', '>=', 'amount')
            ->when(! empty($validated['sale_id']), fn ($q) => $q->where('sale_id', $validated['sale_id']))
            ->when(! empty($validated['saler_id']), function ($q) use ($validated) {
                $q->whereHas('sale', fn ($sq) => $sq->where('saler_id', $validated['saler_id']));
            })
            ->when(! empty($validated['branch_id']), function ($q) use ($validated) {
                $q->whereHas('sale', fn ($sq) => $sq->where('branch_id', $validated['branch_id']));
            })
            ->orderBy('date', 'desc')
            ->paginate($validated['per_page'] ?? 15);

        return SaleDueResource::collection($dues);
    }

    /**
     * Show details of a single sale due.
     */
    public function show(SaleDue $saleDue): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new SaleDueResource($saleDue->load(['sale.saler', 'sale.branch'])),
        ]);
    }

    /**
     * Pay an installment for a sale due.
     */
    public function pay(Request $request, SaleDue $saleDue): JsonResponse
    {
        $remaining = (float) $saleDue->remaining;

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

        $result = DB::transaction(function () use ($saleDue, $amountPaid, $validated) {
            // 1. Update sale due payment
            $saleDue->increment('payment', $amountPaid);

            if (! empty($validated['notes'])) {
                $existingNotes = $saleDue->notes ? $saleDue->notes."\n" : '';
                $saleDue->update([
                    'notes' => $existingNotes.'سداد: '.$amountPaid.' في '.now()->toDateTimeString().' - '.$validated['notes'],
                ]);
            }

            // 2. Update parent Sale
            $sale = $saleDue->sale;
            if ($sale) {
                $sale->increment('paid_amount', $amountPaid);
                $sale->decrement('remaining_amount', $amountPaid);

                // 3. Decrement Saler Balance (reduces debt of the customer/saler)
                if ($sale->saler_id) {
                    Saler::where('id', $sale->saler_id)->decrement('balance', $amountPaid);
                }
            }

            return $saleDue->fresh(['sale.saler', 'sale.branch']);
        });

        $saler = $result->sale?->saler;

        return response()->json([
            'status' => true,
            'message' => 'تم تسجيل سداد القسط بنجاح وخصم المبلغ من حساب العميل/المشتري.',
            'data' => new SaleDueResource($result),
            'meta' => [
                'paid_now' => $amountPaid,
                'due_remaining' => (float) $result->remaining,
                'due_status' => $result->status,
                'sale_remaining' => (float) ($result->sale?->remaining_amount ?? 0),
                'saler_balance' => $saler ? (float) $saler->balance : null,
            ],
        ]);
    }
}
