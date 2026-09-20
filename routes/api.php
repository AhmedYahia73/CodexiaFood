<?php

use App\Http\Controllers\api\admin\AdminController;
use App\Http\Controllers\api\admin\BranchController;
use App\Http\Controllers\api\admin\BusinessSetupController;
use App\Http\Controllers\api\admin\CashierController;
use App\Http\Controllers\api\admin\CashierManController;
use App\Http\Controllers\api\admin\CategoryController;
use App\Http\Controllers\api\admin\DeliveryController;
use App\Http\Controllers\api\admin\DiscountController;
use App\Http\Controllers\api\admin\ExpenseListController;
use App\Http\Controllers\api\admin\FinancialAccountController;
use App\Http\Controllers\api\admin\HallController;
use App\Http\Controllers\api\admin\HallTableController;
use App\Http\Controllers\api\admin\KitchenController;
use App\Http\Controllers\api\admin\ManufactringController;
use App\Http\Controllers\api\admin\MaterialController;
use App\Http\Controllers\api\admin\OrderController;
use App\Http\Controllers\api\admin\PaymentMethodController;
use App\Http\Controllers\api\admin\ProductController;
use App\Http\Controllers\api\admin\ProductManufactringController;
use App\Http\Controllers\api\admin\ProductRecipeController;
use App\Http\Controllers\api\admin\PurchaseController;
use App\Http\Controllers\api\admin\ShiftController;
use App\Http\Controllers\api\admin\SupplierController;
use App\Http\Controllers\api\admin\TaxController;
use App\Http\Controllers\api\admin\WasteController;
use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\cashier\CashierCartController;
use App\Http\Controllers\api\cashier\CashierHomeController;
use App\Http\Controllers\api\cashier\CashierOrderController;
use App\Http\Controllers\api\table\TableHomeController;
use App\Http\Controllers\api\table\TableOrderCartController;
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
    Route::get('branches/select-options', [BranchController::class, 'selectOptions']);
    Route::get('cashier-men/select-options', [CashierManController::class, 'selectOptions']);
    Route::get('cashiers/select-options', [CashierController::class, 'selectOptions']);
    Route::get('deliveries/select-options', [DeliveryController::class, 'selectOptions']);
    Route::get('categories/select-options', [CategoryController::class, 'selectOptions']);
    Route::get('financial-accounts/select-options', [FinancialAccountController::class, 'selectOptions']);
    Route::get('halls/select-options', [HallController::class, 'selectOptions']);
    Route::get('hall-tables/select-options', [HallTableController::class, 'selectOptions']);
    Route::get('kitchens/select-options', [KitchenController::class, 'selectOptions']);
    Route::get('materials/select-options', [MaterialController::class, 'selectOptions']);
    Route::get('orders/select-options', [OrderController::class, 'selectOptions']);
    Route::get('payment-methods/select-options', [PaymentMethodController::class, 'selectOptions']);
    Route::get('product-manufacturings/select-options', [ProductManufactringController::class, 'selectOptions']);
    Route::get('product-recipes/select-options', [ProductRecipeController::class, 'selectOptions']);
    Route::get('products/select-options', [ProductController::class, 'selectOptions']);
    Route::get('shifts/select-options', [ShiftController::class, 'selectOptions']);
    Route::get('suppliers/select-options', [SupplierController::class, 'selectOptions']);
    Route::get('taxes/select-options', [TaxController::class, 'selectOptions']);
    Route::get('wastes/select-options', [WasteController::class, 'selectOptions']);
    Route::get('purchases/select-options', [PurchaseController::class, 'selectOptions']);
    Route::get('manufacturing/select-options', [ManufactringController::class, 'selectOptions']);
    Route::get('manufacturing/specifications', [ManufactringController::class, 'getSpecification']);

    Route::get('orders/pos', [OrderController::class, 'posOrders']);
    Route::get('orders/online', [OrderController::class, 'onlineOrders']);

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
    Route::apiResource('kitchens', KitchenController::class);
    Route::apiResource('materials', MaterialController::class);
    Route::apiResource('orders', OrderController::class);
    Route::apiResource('payment-methods', PaymentMethodController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('product-manufacturings', ProductManufactringController::class);
    Route::apiResource('product-recipes', ProductRecipeController::class);
    Route::apiResource('purchases', PurchaseController::class);
    Route::apiResource('shifts', ShiftController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('taxes', TaxController::class);
    Route::apiResource('wastes', WasteController::class);

    Route::get('manufacturing', [ManufactringController::class, 'index']);
    Route::post('manufacturing', [ManufactringController::class, 'manufacture']);
    Route::get('manufacturing/{manufacturingList}', [ManufactringController::class, 'show']);

    Route::get('business-setup', [BusinessSetupController::class, 'index']);
    Route::post('business-setup', [BusinessSetupController::class, 'update']);
    Route::put('business-setup/{businessSetup?}', [BusinessSetupController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| Cashier Protected Routes (role = cashier_man, cashier, admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:cashier_man', 'role:cashier_man,cashier'])->prefix('cashier')->group(function () {
    Route::get('categories/parents', [CashierHomeController::class, 'parentCategories']);
    Route::get('categories/sub', [CashierHomeController::class, 'subCategories']);
    Route::get('cashiers', [CashierHomeController::class, 'cashiers']);
    Route::get('products', [CashierHomeController::class, 'products']);
    Route::get('products/{product}', [CashierHomeController::class, 'productDetails']);
    Route::get('addons', [CashierHomeController::class, 'addons']);
    Route::get('halls', [CashierHomeController::class, 'halls']);
    Route::get('hall-tables', [CashierHomeController::class, 'hallTables']);
    Route::post('start-shift', [CashierHomeController::class, 'startShift']);
    Route::get('check-start-shift', [CashierHomeController::class, 'checkStartShift']);
    Route::post('end-shift', [CashierHomeController::class, 'endShift']);
    Route::get('business-setup', [CashierHomeController::class, 'businessSetup']);

    Route::delete('cart/clear', [CashierCartController::class, 'clear']);
    Route::apiResource('cart', CashierCartController::class);

    Route::post('orders/checkout', [CashierOrderController::class, 'checkout']);
    Route::apiResource('orders', CashierOrderController::class)->only(['index', 'show']);
});

/*
|--------------------------------------------------------------------------
| Public Table Order Routes (No Auth)
|--------------------------------------------------------------------------
*/
Route::prefix('table')->group(function () {
    Route::get('categories/parents', [TableHomeController::class, 'parentCategories']);
    Route::get('categories/sub', [TableHomeController::class, 'subCategories']);
    Route::get('products', [TableHomeController::class, 'products']);
    Route::get('products/{product}', [TableHomeController::class, 'productDetails']);
    Route::get('addons', [TableHomeController::class, 'addons']);
    Route::get('table/{hallTable}', [TableHomeController::class, 'tableInfo']);

    Route::delete('cart/clear', [TableOrderCartController::class, 'clear']);
    Route::apiResource('cart', TableOrderCartController::class);
});

Route::prefix('table-order')->group(function () {
    Route::get('categories/parents', [TableHomeController::class, 'parentCategories']);
    Route::get('categories/sub', [TableHomeController::class, 'subCategories']);
    Route::get('products', [TableHomeController::class, 'products']);
    Route::get('products/{product}', [TableHomeController::class, 'productDetails']);
    Route::get('addons', [TableHomeController::class, 'addons']);
    Route::get('table/{hallTable}', [TableHomeController::class, 'tableInfo']);

    Route::delete('cart/clear', [TableOrderCartController::class, 'clear']);
    Route::apiResource('cart', TableOrderCartController::class);
});

Route::get('tableOrder/{hallTable}', [TableHomeController::class, 'tableInfo']);
