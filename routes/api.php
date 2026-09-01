<?php

use App\Http\Controllers\api\admin\AdminController;
use App\Http\Controllers\api\admin\BranchController;
use App\Http\Controllers\api\admin\CashierController;
use App\Http\Controllers\api\admin\CashierManController;
use App\Http\Controllers\api\admin\CategoryController;
use App\Http\Controllers\api\admin\DeliveryController;
use App\Http\Controllers\api\admin\DiscountController;
use App\Http\Controllers\api\admin\ExpenseListController;
use App\Http\Controllers\api\admin\FinancialAccountController;
use App\Http\Controllers\api\admin\HallController;
use App\Http\Controllers\api\admin\HallTableController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:admin,cashier_man,branch,kitchen'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin Protected CRUD Routes (role = admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:admin', 'role:admin'])->prefix('admin')->group(function () {
    // Select options endpoints for frontend dropdowns
    Route::get('admins/select-options', [AdminController::class, 'selectOptions']);
    Route::get('cashier-men/select-options', [CashierManController::class, 'selectOptions']);
    Route::get('cashiers/select-options', [CashierController::class, 'selectOptions']);
    Route::get('deliveries/select-options', [DeliveryController::class, 'selectOptions']);
    Route::get('categories/select-options', [CategoryController::class, 'selectOptions']);
    Route::get('financial-accounts/select-options', [FinancialAccountController::class, 'selectOptions']);
    Route::get('halls/select-options', [HallController::class, 'selectOptions']);
    Route::get('hall-tables/select-options', [HallTableController::class, 'selectOptions']);

    Route::apiResource('admins', AdminController::class);
    Route::apiResource('branches', BranchController::class);
    Route::apiResource('cashier-men', CashierManController::class);
    Route::apiResource('cashiers', CashierController::class);
    Route::apiResource('deliveries', DeliveryController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('discounts', DiscountController::class);
    Route::apiResource('expense-lists', ExpenseListController::class);
    Route::apiResource('financial-accounts', FinancialAccountController::class);
    Route::apiResource('halls', HallController::class);
    Route::apiResource('hall-tables', HallTableController::class);
});
