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

        $lists = ExpenseList::with('financialAccount')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (ExpenseList $item) use ($locale) {
                $acc = $item->financialAccount;
                $accName = $acc ? ($this->getLocalized($acc->name, $locale) ?? (string) $acc->name) : null;
                $iconUrl = null;
                if ($acc && $acc->icon) {
                    $iconUrl = (str_starts_with($acc->icon, 'http://') || str_starts_with($acc->icon, 'https://'))
                        ? $acc->icon
                        : url('storage/'.ltrim($acc->icon, '/'));
                }

                return [
                    'id' => $item->id,
                    'name' => $this->getLocalized($item->name, $locale) ?? (string) $item->name,
                    'description' => $this->getLocalized($item->description, $locale),
                    'financial_id' => $item->financial_id,
                    'financial_account_id' => $item->financial_id,
                    'financial_account' => $acc ? [
                        'id' => $acc->id,
                        'name' => $accName,
                        'icon' => $iconUrl,
                    ] : null,
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

        $accountsQuery = FinancialAccount::query()
            ->where(function ($q) {
                $q->where('status', true)->orWhereNull('status');
            })
            ->when($branchId, function ($query) use ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->orderBy('id', 'asc')
            ->get();

        // Fallback 1: If no accounts found for this specific branch, load all active accounts across branches
        if ($accountsQuery->isEmpty()) {
            $accountsQuery = FinancialAccount::query()
                ->where(function ($q) {
                    $q->where('status', true)->orWhereNull('status');
                })
                ->orderBy('id', 'asc')
                ->get();
        }

        // Fallback 2: If still empty, check if any accounts exist regardless of status
        if ($accountsQuery->isEmpty()) {
            $accountsQuery = FinancialAccount::query()
                ->orderBy('id', 'asc')
                ->get();
        }

        // Fallback 3: If table has no accounts at all, create a default cash treasury account
        if ($accountsQuery->isEmpty()) {
            $defaultAcc = FinancialAccount::create([
                'name' => ['ar' => 'الخزينة النقدية الرئيسية', 'en' => 'Main Cash Treasury'],
                'status' => true,
                'balance' => 0.00,
                'branch_id' => $branchId,
            ]);
            $accountsQuery = collect([$defaultAcc]);
        }

        $accounts = $accountsQuery->map(function (FinancialAccount $acc) use ($locale) {
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

                $acc = $expense->financialAccount;
                $iconUrl = null;
                if ($acc && $acc->icon) {
                    $iconUrl = (str_starts_with($acc->icon, 'http://') || str_starts_with($acc->icon, 'https://'))
                        ? $acc->icon
                        : url('storage/'.ltrim($acc->icon, '/'));
                }

                return [
                    'id' => $expense->id,
                    'expense_list_id' => $expense->expense_list_id,
                    'expense_list_name' => $localizedListName,
                    'financial_id' => $expense->financial_id,
                    'financial_account_id' => $expense->financial_id,
                    'financial_account_name' => $localizedAccountName,
                    'financial_account' => $acc ? [
                        'id' => $acc->id,
                        'name' => $localizedAccountName,
                        'icon' => $iconUrl,
                    ] : null,
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
            'financial_id' => 'sometimes|nullable|integer',
            /**
             * Alias for financial_id.
             *
             * @var int|null
             *
             * @example 1
             */
            'financial_account_id' => 'sometimes|nullable|integer',
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

        $cashierMan = auth()->user();
        $cashierManId = $cashierMan?->id;

        $openShift = StartShift::where('cashier_man_id', $cashierManId)
            ->whereNull('end')
            ->latest('start')
            ->first();

        $expenseList = ExpenseList::find($validated['expense_list_id']);
        $financialId = $request->input('financial_id') ?? $request->input('financial_account_id') ?? $expenseList?->financial_id;

        // Verify if account exists in database
        if ($financialId && ! FinancialAccount::where('id', $financialId)->exists()) {
            $financialId = null;
        }

        // Fallback 1: Open shift financial account
        if (! $financialId && $openShift?->financial_account_id && FinancialAccount::where('id', $openShift->financial_account_id)->exists()) {
            $financialId = $openShift->financial_account_id;
        }

        // Fallback 2: Any active financial account in DB
        if (! $financialId) {
            $branchId = $cashierMan?->branch_id;
            $financialId = FinancialAccount::query()
                ->where('status', true)
                ->when($branchId, fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
                ->value('id')
                ?? FinancialAccount::where('status', true)->value('id')
                ?? FinancialAccount::value('id');
        }

        // Fallback 3: If no financial account exists at all, auto-create default
        if (! $financialId) {
            $branchId = $cashierMan?->branch_id;
            $defaultAcc = FinancialAccount::create([
                'name' => ['ar' => 'الخزينة النقدية الرئيسية', 'en' => 'Main Cash Treasury'],
                'status' => true,
                'balance' => 0.00,
                'branch_id' => $branchId,
            ]);
            $financialId = $defaultAcc->id;
        }

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
