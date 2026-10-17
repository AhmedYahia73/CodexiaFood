<?php

namespace App\Http\Controllers\api\cashier;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseList;
use App\Models\FinancialAccount;
use App\Models\StartShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierExpenseController extends Controller
{
    private function getLocale(Request $request): string
    {
        $lang = $request->query('lang')
            ?? $request->input('lang')
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? app()->getLocale();

        return str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';
    }

    private function getLocalized(mixed $value, string $locale): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ((str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) || (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
        }

        if (is_array($value)) {
            return $value[$locale] ?? $value['ar'] ?? $value['en'] ?? (isset($value[0]) && is_string($value[0]) ? $value[0] : null);
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Get expense lists for cashier selection.
     */
    public function expenseLists(Request $request): JsonResponse
    {
        $request->validate([
            /**
             * Language code ('ar' or 'en').
             *
             * @var string|null
             *
             * @example "ar"
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);

        $lists = ExpenseList::query()
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (ExpenseList $item) use ($locale) {
                return [
                    'id' => $item->id,
                    'name' => $this->getLocalized($item->name, $locale) ?? (string) $item->name,
                    'description' => $this->getLocalized($item->description, $locale),
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'تم جلب بنود المصروفات بنجاح',
            'data' => $lists,
        ]);
    }

    /**
     * Get active financial accounts for cashier.
     */
    public function financialAccounts(Request $request): JsonResponse
    {
        $request->validate([
            /**
             * Language code ('ar' or 'en').
             *
             * @var string|null
             *
             * @example "ar"
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
            /**
             * Filter by branch id.
             *
             * @var int|null
             */
            'branch_id' => 'sometimes|nullable|integer|exists:branches,id',
        ]);

        $locale = $this->getLocale($request);
        $branchId = $request->query('branch_id') ?? auth()->user()?->branch_id;

        $accounts = FinancialAccount::query()
            ->where('status', true)
            ->when($branchId, function ($query) use ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (FinancialAccount $acc) use ($locale) {
                $name = $acc->name;
                $localizedName = is_array($name)
                    ? ($name[$locale] ?? $name['ar'] ?? $name['en'] ?? '')
                    : (string) $name;

                $iconUrl = null;
                if ($acc->icon) {
                    $iconUrl = (str_starts_with($acc->icon, 'http://') || str_starts_with($acc->icon, 'https://'))
                        ? $acc->icon
                        : url('storage/'.ltrim($acc->icon, '/'));
                }

                return [
                    'id' => $acc->id,
                    'name' => $localizedName,
                    'icon' => $iconUrl,
                    'balance' => (float) $acc->balance,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'تم جلب الحسابات المالية بنجاح',
            'data' => $accounts,
        ]);
    }

    /**
     * List expenses recorded during shift or by cashier.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            /**
             * Filter expenses by shift ID.
             *
             * @var int|null
             */
            'shift_id' => 'sometimes|nullable|integer|exists:start_shifts,id',
            /**
             * Language code ('ar' or 'en').
             *
             * @var string|null
             *
             * @example "ar"
             */
            'lang' => 'sometimes|nullable|string|in:ar,en',
        ]);

        $locale = $this->getLocale($request);
        $cashierManId = auth()->id();

        $openShift = StartShift::where('cashier_man_id', $cashierManId)
            ->whereNull('end')
            ->latest('start')
            ->first();

        $shiftId = $request->query('shift_id') ?? $openShift?->id;

        $expenses = Expense::with(['expenseList', 'financialAccount', 'cashier'])
            ->when($shiftId, function ($q) use ($shiftId, $openShift) {
                $q->where(function ($query) use ($shiftId, $openShift) {
                    $query->where('shift_id', $shiftId);
                    if ($openShift && $openShift->id == $shiftId) {
                        $query->orWhere(function ($sub) use ($openShift) {
                            $sub->where('cashier_man_id', $openShift->cashier_man_id)
                                ->where('created_at', '>=', $openShift->start);
                        });
                    }
                });
            }, function ($q) use ($cashierManId) {
                $q->where('cashier_man_id', $cashierManId);
            })
            ->latest('id')
            ->get()
            ->map(function (Expense $expense) use ($locale) {
                $listName = $expense->expenseList?->name;
                $localizedListName = is_array($listName)
                    ? ($listName[$locale] ?? $listName['ar'] ?? $listName['en'] ?? '')
                    : (string) $listName;

                $accountName = $expense->financialAccount?->name;
                $localizedAccountName = is_array($accountName)
                    ? ($accountName[$locale] ?? $accountName['ar'] ?? $accountName['en'] ?? '')
                    : (string) $accountName;

                return [
                    'id' => $expense->id,
                    'expense_list_id' => $expense->expense_list_id,
                    'expense_list_name' => $localizedListName,
                    'financial_id' => $expense->financial_id,
                    'financial_account_name' => $localizedAccountName,
                    'amount' => (float) $expense->amount,
                    'description' => $expense->description,
                    'shift_id' => $expense->shift_id,
                    'cashier_id' => $expense->cashier_id,
                    'cashier_man_id' => $expense->cashier_man_id,
                    'created_at' => $expense->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'تم جلب المصروفات بنجاح',
            'data' => $expenses,
            'total_amount' => round((float) $expenses->sum('amount'), 2),
        ]);
    }

    /**
     * Record a new expense for the cashier.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /**
             * The expense list category ID.
             *
             * @var int
             *
             * @example 1
             */
            'expense_list_id' => 'required|integer|exists:expense_lists,id',
            /**
             * The financial account ID the expense is drawn from.
             *
             * @var int|null
             *
             * @example 1
             */
            'financial_id' => 'sometimes|nullable|integer|exists:financial_accounts,id',
            /**
             * Alias for financial_id.
             *
             * @var int|null
             *
             * @example 1
             */
            'financial_account_id' => 'sometimes|nullable|integer|exists:financial_accounts,id',
            /**
             * Expense monetary amount.
             *
             * @var float
             *
             * @example 50.00
             */
            'amount' => 'required|numeric|min:0.01',
            /**
             * Optional description or notes for the expense.
             *
             * @var string|null
             *
             * @example "شراء أدوات نظافة"
             */
            'description' => 'sometimes|nullable|string',
            /**
             * Alias for description.
             *
             * @var string|null
             */
            'note' => 'sometimes|nullable|string',
        ]);

        $financialId = $request->input('financial_id') ?? $request->input('financial_account_id');
        if (! $financialId) {
            return response()->json([
                'status' => false,
                'message' => 'الحساب المالي مطلوب',
                'errors' => [
                    'financial_id' => ['يرجى تحديد الحساب المالي للمصروف.'],
                ],
            ], 422);
        }

        $cashierMan = auth()->user();
        $cashierManId = $cashierMan?->id;

        $openShift = StartShift::where('cashier_man_id', $cashierManId)
            ->whereNull('end')
            ->latest('start')
            ->first();

        $cashierId = $cashierMan?->cashier_id ?? $openShift?->cashier_id;
        $description = $validated['description'] ?? $validated['note'] ?? null;

        $expense = Expense::create([
            'expense_list_id' => $validated['expense_list_id'],
            'financial_id' => $financialId,
            'cashier_id' => $cashierId,
            'cashier_man_id' => $cashierManId,
            'shift_id' => $openShift?->id,
            'amount' => round((float) $validated['amount'], 2),
            'description' => $description,
        ]);

        $locale = $this->getLocale($request);
        $expense->load(['expenseList', 'financialAccount']);

        $listName = $expense->expenseList?->name;
        $localizedListName = is_array($listName)
            ? ($listName[$locale] ?? $listName['ar'] ?? $listName['en'] ?? '')
            : (string) $listName;

        $accountName = $expense->financialAccount?->name;
        $localizedAccountName = is_array($accountName)
            ? ($accountName[$locale] ?? $accountName['ar'] ?? $accountName['en'] ?? '')
            : (string) $accountName;

        return response()->json([
            'status' => true,
            'message' => 'تم تسجيل المصروف بنجاح',
            'data' => [
                'id' => $expense->id,
                'expense_list_id' => $expense->expense_list_id,
                'expense_list_name' => $localizedListName,
                'financial_id' => $expense->financial_id,
                'financial_account_name' => $localizedAccountName,
                'amount' => (float) $expense->amount,
                'description' => $expense->description,
                'shift_id' => $expense->shift_id,
                'cashier_id' => $expense->cashier_id,
                'cashier_man_id' => $expense->cashier_man_id,
                'created_at' => $expense->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
