<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StartShiftResource;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\StartShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Get select options and filter lists (supporting branch_id, cashier_id, cashier_man_id, lang).
     *
     * Returns lists of branches, cashiers, and cashier men with id and localized name.
     */
    public function selectOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'cashier_id' => 'nullable|integer|exists:cashiers,id',
            'cashier_man_id' => 'nullable|integer|exists:cashier_men,id',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        return response()->json([
            'status' => true,
            'data' => $this->getListsData($request),
        ]);
    }

    /**
     * StartShift Report with filters.
     *
     * Filter and display shifts report by cashier_id, cashier_man_id, branch_id, start, and end.
     * Displays default_total_amount, cashier name, branch name, cashier man, start, end, total_mony, and deficit.
     */
    public function startShiftReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:-1',
            'cashier_id' => 'nullable|integer|exists:cashiers,id',
            'cashier_man_id' => 'nullable|integer|exists:cashier_men,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $query = StartShift::with(['branch', 'cashier', 'cashierMan'])
            ->latest('start');

        // Filter by cashier_id
        if (! empty($validated['cashier_id'])) {
            $query->where('cashier_id', $validated['cashier_id']);
        }

        // Filter by cashier_man_id
        if (! empty($validated['cashier_man_id'])) {
            $query->where('cashier_man_id', $validated['cashier_man_id']);
        }

        // Filter by branch_id
        if (! empty($validated['branch_id'])) {
            $query->where('branch_id', $validated['branch_id']);
        }

        // Filter by start date / datetime
        if (! empty($validated['start'])) {
            $startDate = $validated['start'];
            if (strlen((string) $startDate) === 10) {
                $query->whereDate('start', '>=', $startDate);
            } else {
                $query->where('start', '>=', $startDate);
            }
        }

        // Filter by end date / datetime
        if (! empty($validated['end'])) {
            $endDate = $validated['end'];
            if (strlen((string) $endDate) === 10) {
                $query->whereDate('end', '<=', $endDate);
            } else {
                $query->where('end', '<=', $endDate);
            }
        }

        // Calculate summary totals on the filtered query
        $totalDefaultAmount = (float) (clone $query)->sum('default_total_amount');
        $totalCollectedMony = (float) (clone $query)->whereNotNull('total_mony')->sum('total_mony');
        $totalDeficit = round($totalDefaultAmount - $totalCollectedMony, 2);
        $totalCount = (clone $query)->count();

        $page = (int) ($validated['page'] ?? $request->get('page', 1));
        $perPage = (int) ($validated['per_page'] ?? $request->get('per_page', 15));

        if ($perPage === -1) {
            $shifts = $query->get();
            $itemsData = StartShiftResource::collection($shifts);
            $links = null;
            $meta = [
                'current_page' => 1,
                'from' => $totalCount > 0 ? 1 : null,
                'last_page' => 1,
                'per_page' => $totalCount,
                'to' => $totalCount,
                'total' => $totalCount,
            ];
            $paginatedData = [
                'data' => $itemsData,
                'total' => $totalCount,
                'links' => $links,
                'meta' => $meta,
            ];
        } else {
            $paginated = $query->paginate(perPage: $perPage, page: $page);
            $paginatedResponse = StartShiftResource::collection($paginated)->response()->getData(true);
            $itemsData = $paginatedResponse['data'];
            $links = $paginatedResponse['links'];
            $meta = $paginatedResponse['meta'];
            $paginatedData = $paginatedResponse;
        }

        return response()->json([
            'status' => true,
            'summary' => [
                'total_default_amount' => round($totalDefaultAmount, 2),
                'total_collected_mony' => round($totalCollectedMony, 2),
                'total_deficit' => $totalDeficit,
                'shifts_count' => $totalCount,
            ],
            'data' => $itemsData,
            'links' => $links,
            'meta' => $meta,
            'shifts' => $paginatedData,
            'select_options' => $this->getListsData($request),
        ]);
    }

    /**
     * Helper to get list options for branches, cashiers, and cashier men with id and localized name.
     */
    private function getListsData(Request $request): array
    {
        $lang = $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? app()->getLocale();
        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        $branchId = $request->input('branch_id');
        $cashierId = $request->input('cashier_id');
        $cashierManId = $request->input('cashier_man_id');

        // Branches (id, name localized)
        $branches = Branch::select('id', 'name')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => is_array($b->name) ? ($b->name[$locale] ?? $b->name['ar'] ?? $b->name['en'] ?? '') : $b->name,
            ]);

        // Cashiers (id, name localized, branch_id)
        $cashiersQuery = Cashier::select('id', 'name', 'branch_id');
        if ($branchId) {
            $cashiersQuery->where('branch_id', $branchId);
        }
        if ($cashierId) {
            $cashiersQuery->where('id', $cashierId);
        }
        $cashiers = $cashiersQuery->get()->map(function ($c) use ($locale) {
            $name = $c->name;
            if (is_string($name)) {
                $decoded = json_decode($name, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $name = $decoded;
                }
            }

            return [
                'id' => $c->id,
                'name' => is_array($name) ? ($name[$locale] ?? $name['ar'] ?? $name['en'] ?? '') : $name,
                'branch_id' => $c->branch_id,
            ];
        });

        // Cashier Men (id, name, branch_id)
        $cashierMenQuery = CashierMan::select('id', 'name', 'branch_id');
        if ($branchId) {
            $cashierMenQuery->where('branch_id', $branchId);
        }
        if ($cashierManId) {
            $cashierMenQuery->where('id', $cashierManId);
        }
        $cashierMen = $cashierMenQuery->get()->map(fn ($cm) => [
            'id' => $cm->id,
            'name' => $cm->name,
            'branch_id' => $cm->branch_id,
        ]);

        return [
            'branches' => $branches,
            'cashiers' => $cashiers,
            'cashier_men' => $cashierMen,
        ];
    }
}
