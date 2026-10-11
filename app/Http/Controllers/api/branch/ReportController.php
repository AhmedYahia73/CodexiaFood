<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Resources\StartShiftResource;
use App\Models\Branch;
use App\Models\Cashier;
use App\Models\CashierMan;
use App\Models\StartShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends BaseBranchController
{
    /**
     * Get select options and filter lists scoped to the authenticated branch.
     */
    public function selectOptions(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => 'nullable|string|in:ar,en',
        ]);

        return response()->json([
            'status' => true,
            'data' => $this->getListsData($request),
        ]);
    }

    /**
     * StartShift Report with filters for the authenticated branch.
     */
    public function startShiftReport(Request $request): JsonResponse
    {
        $branchId = $this->getBranchId();

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:-1',
            'cashier_id' => 'nullable|integer|exists:cashiers,id',
            'cashier_man_id' => 'nullable|integer|exists:cashier_men,id',
            'branch_id' => 'sometimes|nullable|integer',
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $query = StartShift::with(['branch', 'cashier', 'cashierMan'])
            ->where('branch_id', $branchId)
            ->latest('start');

        if (! empty($validated['cashier_id'])) {
            $query->where('cashier_id', $validated['cashier_id']);
        }

        if (! empty($validated['cashier_man_id'])) {
            $query->where('cashier_man_id', $validated['cashier_man_id']);
        }

        if (! empty($validated['start'])) {
            $startDate = $validated['start'];
            if (strlen((string) $startDate) === 10) {
                $query->whereDate('start', '>=', $startDate);
            } else {
                $query->where('start', '>=', $startDate);
            }
        }

        if (! empty($validated['end'])) {
            $endDate = $validated['end'];
            if (strlen((string) $endDate) === 10) {
                $query->whereDate('end', '<=', $endDate);
            } else {
                $query->where('end', '<=', $endDate);
            }
        }

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

    private function getListsData(Request $request): array
    {
        $branchId = $this->getBranchId();

        $lang = $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? $request->header('lang')
            ?? app()->getLocale();
        $locale = str_starts_with(strtolower((string) $lang), 'en') ? 'en' : 'ar';

        $cashierId = $request->input('cashier_id');
        $cashierManId = $request->input('cashier_man_id');

        $branches = Branch::where('id', $branchId)->select('id', 'name')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => is_array($b->name) ? ($b->name[$locale] ?? $b->name['ar'] ?? $b->name['en'] ?? '') : $b->name,
            ]);

        $cashiersQuery = Cashier::where('branch_id', $branchId)->select('id', 'name', 'branch_id');
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

        $cashierMenQuery = CashierMan::where('branch_id', $branchId)->select('id', 'name', 'branch_id');
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
