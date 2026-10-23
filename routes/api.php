<?php

use App\Http\Controllers\api\admin\AddonController;
use App\Http\Controllers\api\admin\AdminController;
use App\Http\Controllers\api\admin\BranchController;
use App\Http\Controllers\api\admin\BusinessSetupController;
use App\Http\Controllers\api\admin\CashierController;
use App\Http\Controllers\api\admin\CashierManController;
use App\Http\Controllers\api\admin\CategoryController;
use App\Http\Controllers\api\admin\DashboardController;
use App\Http\Controllers\api\admin\DeliveryController;
use App\Http\Controllers\api\admin\DiscountController;
use App\Http\Controllers\api\admin\ExpenseListController;
use App\Http\Controllers\api\admin\FinancialAccountController;
use App\Http\Controllers\api\admin\HallController;
use App\Http\Controllers\api\admin\HallTableController;
use App\Http\Controllers\api\admin\InventoryMaterialController;
use App\Http\Controllers\api\admin\InventoryRecipeController;
use App\Http\Controllers\api\admin\KitchenController;
use App\Http\Controllers\api\admin\KitchenProductController;
use App\Http\Controllers\api\admin\ManufactringController;
use App\Http\Controllers\api\admin\MaterialController;
use App\Http\Controllers\api\admin\OrderController;
use App\Http\Controllers\api\admin\PaymentMethodController;
use App\Http\Controllers\api\admin\ProductController;
use App\Http\Controllers\api\admin\ProductManufactringController;
use App\Http\Controllers\api\admin\ProductRecipeController;
use App\Http\Controllers\api\admin\PurchaseController;
use App\Http\Controllers\api\admin\ReportController;
use App\Http\Controllers\api\admin\SaleController;
use App\Http\Controllers\api\admin\SalerController;
use App\Http\Controllers\api\admin\ShiftController;
use App\Http\Controllers\api\admin\SupplierController;
use App\Http\Controllers\api\admin\TaxController;
use App\Http\Controllers\api\admin\WasteController;
use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\branch\AddonController as BranchAddonController;
use App\Http\Controllers\api\branch\CashierController as BranchCashierController;
use App\Http\Controllers\api\branch\CashierManController as BranchCashierManController;
use App\Http\Controllers\api\branch\DashboardController as BranchDashboardController;
use App\Http\Controllers\api\branch\DeliveryController as BranchDeliveryController;
use App\Http\Controllers\api\branch\FinancialAccountController as BranchFinancialAccountController;
use App\Http\Controllers\api\branch\HallController as BranchHallController;
use App\Http\Controllers\api\branch\HallTableController as BranchHallTableController;
use App\Http\Controllers\api\branch\InventoryMaterialController as BranchInventoryMaterialController;
use App\Http\Controllers\api\branch\InventoryRecipeController as BranchInventoryRecipeController;
use App\Http\Controllers\api\branch\KitchenController as BranchKitchenController;
use App\Http\Controllers\api\branch\KitchenProductController as BranchKitchenProductController;
use App\Http\Controllers\api\branch\ManufactringController as BranchManufactringController;
use App\Http\Controllers\api\branch\OrderController as BranchOrderController;
use App\Http\Controllers\api\branch\PurchaseController as BranchPurchaseController;
use App\Http\Controllers\api\branch\ReportController as BranchReportController;
use App\Http\Controllers\api\branch\ShiftController as BranchShiftController;
use App\Http\Controllers\api\branch\WasteController as BranchWasteController;
use App\Http\Controllers\api\cashier\CashierCartController;
use App\Http\Controllers\api\cashier\CashierExpenseController;
use App\Http\Controllers\api\cashier\CashierHomeController;
use App\Http\Controllers\api\cashier\CashierOrderController;
use App\Http\Controllers\api\kitchen\KitchenOrderController;
use App\Http\Controllers\api\table\TableHomeController;
use App\Http\Controllers\api\table\TableOrderCartController;
use App\Http\Controllers\api\user\UserCartController;
use App\Http\Controllers\api\user\UserHomeController;
use App\Http\Controllers\api\user\UserOrderController;
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
    Route::get('addons/lists', [AddonController::class, 'lists']);
    Route::get('addons/select-options', [AddonController::class, 'selectOptions']);
    Route::get('admins/select-options', [AdminController::class, 'selectOptions']);
    Route::get('branches/select-options', [BranchController::class, 'selectOptions']);
    Route::get('cashier-men/select-options', [CashierManController::class, 'selectOptions']);
    Route::get('cashiers/select-options', [CashierController::class, 'selectOptions']);
    Route::get('deliveries/select-options', [DeliveryController::class, 'selectOptions']);
    Route::get('deliveries/select', [DeliveryController::class, 'select']);
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
    Route::get('salers/select-options', [SalerController::class, 'selectOptions']);
    Route::get('taxes/select-options', [TaxController::class, 'selectOptions']);
    Route::get('wastes/select-options', [WasteController::class, 'selectOptions']);
    Route::get('purchases/select-options', [PurchaseController::class, 'selectOptions']);
    Route::get('sales/select-options', [SaleController::class, 'selectOptions']);
    Route::get('manufacturing/select-options', [ManufactringController::class, 'selectOptions']);
    Route::get('manufacturing/specifications', [ManufactringController::class, 'getSpecification']);
    Route::get('reports/start-shifts/select-options', [ReportController::class, 'selectOptions']);
    Route::get('reports/start-shifts/lists', [ReportController::class, 'selectOptions']);
    Route::get('start-shifts/select-options', [ReportController::class, 'selectOptions']);
    Route::get('start-shifts/lists', [ReportController::class, 'selectOptions']);
    Route::get('reports/start-shifts', [ReportController::class, 'startShiftReport']);
    Route::get('start-shifts/report', [ReportController::class, 'startShiftReport']);

    Route::get('orders/pos', [OrderController::class, 'posOrders']);
    Route::get('orders/online', [OrderController::class, 'onlineOrders']);
    Route::get('orders/check-new', [OrderController::class, 'checkNewOrders']);
    Route::post('orders/check-new', [OrderController::class, 'checkNewOrders']);
    Route::match(['put', 'patch', 'post'], 'orders/{order}/status', [OrderController::class, 'updateStatus']);

    // Kitchen product assignment
    Route::get('kitchen-products/unassigned', [KitchenProductController::class, 'unassigned']);
    Route::get('kitchen-products/{kitchen}', [KitchenProductController::class, 'kitchenProducts']);
    Route::get('kitchen-products', [KitchenProductController::class, 'kitchenProducts']);
    Route::match(['put', 'post'], 'kitchen-products/assign', [KitchenProductController::class, 'assign']);

    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/statistics', [DashboardController::class, 'statistics']);

    Route::match(['put', 'patch', 'post'], 'addons/{addon}', [AddonController::class, 'update']);
    Route::apiResource('addons', AddonController::class);
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
    Route::apiResource('sales', SaleController::class);
    Route::apiResource('shifts', ShiftController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('salers', SalerController::class);
    Route::apiResource('taxes', TaxController::class);
    Route::apiResource('wastes', WasteController::class);

    Route::get('manufacturing', [ManufactringController::class, 'index']);
    Route::post('manufacturing', [ManufactringController::class, 'manufacture']);
    Route::get('manufacturing/{manufacturingList}', [ManufactringController::class, 'show']);

    Route::get('business-setup', [BusinessSetupController::class, 'index']);
    Route::match(['put', 'patch', 'post'], 'business-setup/{businessSetup?}', [BusinessSetupController::class, 'update']);

    // Inventory routes for Product Recipes
    Route::prefix('inventory/recipes')->group(function () {
        Route::get('select-options', [InventoryRecipeController::class, 'selectOptions']);
        Route::get('pending', [InventoryRecipeController::class, 'pending']);
        Route::get('history', [InventoryRecipeController::class, 'history']);
        Route::post('/', [InventoryRecipeController::class, 'store']);
        Route::get('{inventory}', [InventoryRecipeController::class, 'show']);
        Route::put('{inventory}/items', [InventoryRecipeController::class, 'updateActualStocks']);
        Route::put('{inventory}/status', [InventoryRecipeController::class, 'changeStatus']);
    });
    Route::put('inventory/recipe-items/{item}', [InventoryRecipeController::class, 'updateItem']);

    // Inventory routes for Materials
    Route::prefix('inventory/materials')->group(function () {
        Route::get('select-options', [InventoryMaterialController::class, 'selectOptions']);
        Route::get('pending', [InventoryMaterialController::class, 'pending']);
        Route::get('history', [InventoryMaterialController::class, 'history']);
        Route::post('/', [InventoryMaterialController::class, 'store']);
        Route::get('{inventory}', [InventoryMaterialController::class, 'show']);
        Route::put('{inventory}/items', [InventoryMaterialController::class, 'updateActualStocks']);
        Route::put('{inventory}/status', [InventoryMaterialController::class, 'changeStatus']);
    });
    Route::put('inventory/material-items/{item}', [InventoryMaterialController::class, 'updateItem']);
});

/*
|--------------------------------------------------------------------------
| Branch Protected CRUD Routes (role = branch)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:branch', 'role:branch'])->prefix('branch')->group(function () {
    // Select options endpoints for frontend dropdowns
    Route::get('addons/lists', [BranchAddonController::class, 'lists']);
    Route::get('addons/select-options', [BranchAddonController::class, 'selectOptions']);
    Route::get('cashier-men/select-options', [BranchCashierManController::class, 'selectOptions']);
    Route::get('cashiers/select-options', [BranchCashierController::class, 'selectOptions']);
    Route::get('deliveries/select-options', [BranchDeliveryController::class, 'selectOptions']);
    Route::get('deliveries/select', [BranchDeliveryController::class, 'select']);
    Route::get('financial-accounts/select-options', [BranchFinancialAccountController::class, 'selectOptions']);
    Route::get('halls/select-options', [BranchHallController::class, 'selectOptions']);
    Route::get('hall-tables/select-options', [BranchHallTableController::class, 'selectOptions']);
    Route::get('kitchens/select-options', [BranchKitchenController::class, 'selectOptions']);
    Route::get('orders/select-options', [BranchOrderController::class, 'selectOptions']);
    Route::get('shifts/select-options', [BranchShiftController::class, 'selectOptions']);
    Route::get('wastes/select-options', [BranchWasteController::class, 'selectOptions']);
    Route::get('purchases/select-options', [BranchPurchaseController::class, 'selectOptions']);
    Route::get('manufacturing/select-options', [BranchManufactringController::class, 'selectOptions']);
    Route::get('manufacturing/specifications', [BranchManufactringController::class, 'getSpecification']);
    Route::get('reports/start-shifts/select-options', [BranchReportController::class, 'selectOptions']);
    Route::get('reports/start-shifts/lists', [BranchReportController::class, 'selectOptions']);
    Route::get('start-shifts/select-options', [BranchReportController::class, 'selectOptions']);
    Route::get('start-shifts/lists', [BranchReportController::class, 'selectOptions']);
    Route::get('reports/start-shifts', [BranchReportController::class, 'startShiftReport']);
    Route::get('start-shifts/report', [BranchReportController::class, 'startShiftReport']);

    Route::get('orders/pos', [BranchOrderController::class, 'posOrders']);
    Route::get('orders/online', [BranchOrderController::class, 'onlineOrders']);
    Route::get('orders/check-new', [BranchOrderController::class, 'checkNewOrders']);
    Route::post('orders/check-new', [BranchOrderController::class, 'checkNewOrders']);
    Route::match(['put', 'patch', 'post'], 'orders/{order}/status', [BranchOrderController::class, 'updateStatus']);

    // Kitchen product assignment
    Route::get('kitchen-products/unassigned', [BranchKitchenProductController::class, 'unassigned']);
    Route::get('kitchen-products/{kitchen}', [BranchKitchenProductController::class, 'kitchenProducts']);
    Route::get('kitchen-products', [BranchKitchenProductController::class, 'kitchenProducts']);
    Route::match(['put', 'post'], 'kitchen-products/assign', [BranchKitchenProductController::class, 'assign']);

    Route::get('dashboard', [BranchDashboardController::class, 'index']);
    Route::get('dashboard/statistics', [BranchDashboardController::class, 'statistics']);

    Route::match(['put', 'patch', 'post'], 'addons/{addon}', [BranchAddonController::class, 'update']);
    Route::apiResource('addons', BranchAddonController::class);
    Route::apiResource('cashier-men', BranchCashierManController::class);
    Route::apiResource('cashiers', BranchCashierController::class);
    Route::apiResource('deliveries', BranchDeliveryController::class);
    Route::apiResource('financial-accounts', BranchFinancialAccountController::class);
    Route::apiResource('halls', BranchHallController::class);
    Route::apiResource('hall-tables', BranchHallTableController::class);
    Route::apiResource('kitchens', BranchKitchenController::class);
    Route::apiResource('orders', BranchOrderController::class);
    Route::apiResource('purchases', BranchPurchaseController::class);
    Route::apiResource('shifts', BranchShiftController::class);
    Route::apiResource('wastes', BranchWasteController::class);

    Route::get('manufacturing', [BranchManufactringController::class, 'index']);
    Route::post('manufacturing', [BranchManufactringController::class, 'manufacture']);
    Route::get('manufacturing/{manufacturingList}', [BranchManufactringController::class, 'show']);

    // Inventory routes for Product Recipes
    Route::prefix('inventory/recipes')->group(function () {
        Route::get('select-options', [BranchInventoryRecipeController::class, 'selectOptions']);
        Route::get('pending', [BranchInventoryRecipeController::class, 'pending']);
        Route::get('history', [BranchInventoryRecipeController::class, 'history']);
        Route::post('/', [BranchInventoryRecipeController::class, 'store']);
        Route::get('{inventory}', [BranchInventoryRecipeController::class, 'show']);
        Route::put('{inventory}/items', [BranchInventoryRecipeController::class, 'updateActualStocks']);
        Route::put('{inventory}/status', [BranchInventoryRecipeController::class, 'changeStatus']);
    });
    Route::put('inventory/recipe-items/{item}', [BranchInventoryRecipeController::class, 'updateItem']);

    // Inventory routes for Materials
    Route::prefix('inventory/materials')->group(function () {
        Route::get('select-options', [BranchInventoryMaterialController::class, 'selectOptions']);
        Route::get('pending', [BranchInventoryMaterialController::class, 'pending']);
        Route::get('history', [BranchInventoryMaterialController::class, 'history']);
        Route::post('/', [BranchInventoryMaterialController::class, 'store']);
        Route::get('{inventory}', [BranchInventoryMaterialController::class, 'show']);
        Route::put('{inventory}/items', [BranchInventoryMaterialController::class, 'updateActualStocks']);
        Route::put('{inventory}/status', [BranchInventoryMaterialController::class, 'changeStatus']);
    });
    Route::put('inventory/material-items/{item}', [BranchInventoryMaterialController::class, 'updateItem']);
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
    Route::get('financial-accounts', [CashierHomeController::class, 'financialAccounts']);
    Route::post('start-shift', [CashierHomeController::class, 'startShift']);
    Route::get('check-start-shift', [CashierHomeController::class, 'checkStartShift']);
    Route::post('end-shift', [CashierHomeController::class, 'endShift']);

    Route::get('expense-lists', [CashierExpenseController::class, 'expenseLists']);
    Route::get('expenses', [CashierExpenseController::class, 'index']);
    Route::post('expenses', [CashierExpenseController::class, 'store']);

    Route::delete('cart/clear', [CashierCartController::class, 'clear']);
    Route::apiResource('cart', CashierCartController::class);

    Route::post('orders/checkout', [CashierOrderController::class, 'checkout']);
    Route::match(['put', 'patch', 'post'], 'orders/{order}/status', [CashierOrderController::class, 'updateStatus']);
    Route::get('deliveries/select', [CashierOrderController::class, 'deliveriesSelect']);
    Route::get('deliveries', [CashierOrderController::class, 'deliveriesSelect']);
    Route::get('delivery-orders/unpaid', [CashierOrderController::class, 'unpaidDeliveries']);
    Route::post('delivery-orders/settle-payment', [CashierOrderController::class, 'settleDeliveryOrdersPayment']);
    Route::apiResource('orders', CashierOrderController::class)->only(['index', 'show']);
    Route::get('business-setup', [CashierHomeController::class, 'businessSetup']);
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

Route::get('tableOrder/{hallTable?}', [TableHomeController::class, 'tableInfo']);
Route::get('table/{hallTable?}', [TableHomeController::class, 'tableInfo']);

/*
|--------------------------------------------------------------------------
| Public User Home Routes (No Auth)
|--------------------------------------------------------------------------
*/
Route::prefix('user')->group(function () {
    Route::get('categories/parents', [UserHomeController::class, 'parentCategories']);
    Route::get('categories/sub', [UserHomeController::class, 'subCategories']);
    Route::get('products', [UserHomeController::class, 'products']);
    Route::get('products/{product}', [UserHomeController::class, 'productDetails']);
    Route::get('addons', [UserHomeController::class, 'addons']);
    Route::get('business-setup', [UserHomeController::class, 'businessSetup']);
    Route::get('menu', [UserHomeController::class, 'menu']);
    Route::get('payment-methods', [UserOrderController::class, 'paymentMethods']);

    Route::delete('cart/clear', [UserCartController::class, 'clear']);
    Route::apiResource('cart', UserCartController::class);

    Route::post('orders/checkout', [UserOrderController::class, 'checkout']);
    Route::get('orders/{order}', [UserOrderController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Kitchen Protected Routes (role = kitchen)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:kitchen', 'role:kitchen'])->prefix('kitchen')->group(function () {
    Route::get('orders', [KitchenOrderController::class, 'index']);
    Route::match(['put', 'post'], 'orders/{kitchenOrder}/done', [KitchenOrderController::class, 'markDone']);
});
