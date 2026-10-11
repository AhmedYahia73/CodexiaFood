<?php

namespace App\Http\Controllers\api\branch;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class BaseBranchController extends Controller
{
    /**
     * Get the authenticated branch ID.
     */
    protected function getBranchId(): int
    {
        $id = auth('branch')->id() ?? auth()->id();

        if (! $id) {
            abort(401, 'Unauthenticated branch session.');
        }

        return (int) $id;
    }

    /**
     * Ensure that the given model belongs to the authenticated branch.
     *
     * @throws HttpException
     */
    protected function ensureBelongsToBranch(Model $model, string $branchColumn = 'branch_id'): void
    {
        if ((int) $model->{$branchColumn} !== $this->getBranchId()) {
            abort(403, 'Unauthorized action: this resource does not belong to your branch.');
        }
    }
}
