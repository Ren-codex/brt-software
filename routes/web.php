<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [App\Http\Controllers\WelcomeController::class, 'index'])->middleware('guest')->name('welcome');;

// Landing Page Routes
Route::get('/landing', function () {
    return Inertia::render('Landing');
})->middleware('guest')->name('landing');

Route::middleware(['2fa','auth','verified','is_active'])->group(function () {
    Route::get('/', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::resource('/sales', App\Http\Controllers\Modules\SalesOrderController::class);
    Route::resource('/sales-orders', App\Http\Controllers\Modules\SalesOrderController::class);
    Route::post('/sales-orders/adjustment/{id}', [App\Http\Controllers\Modules\SalesAdjustmentController::class, 'store']);
    Route::resource('/ar-invoices', App\Http\Controllers\Modules\ArInvoiceController::class);
    Route::resource('/employees', App\Http\Controllers\Modules\EmployeeController::class);
    Route::resource('/customers', App\Http\Controllers\Modules\CustomerController::class);

    // Make revenue reports available to all authenticated users (not just administrators)
    Route::get('/api/revenue-reports', [App\Http\Controllers\Modules\RevenueReportController::class, 'index']);

    Route::middleware(['role:Administrator'])->group(function () {
        Route::resource('/users', App\Http\Controllers\System\UserController::class);
        Route::resource('/libraries/suppliers', App\Http\Controllers\Libraries\SupplierController::class);
        Route::resource('/libraries/roles', App\Http\Controllers\Libraries\RoleController::class);
        Route::resource('/libraries/brands', App\Http\Controllers\Libraries\BrandController::class);
        Route::resource('/libraries/units', App\Http\Controllers\Libraries\UnitController::class);
        Route::resource('/libraries/products', App\Http\Controllers\Libraries\ProductController::class);
        Route::resource('/libraries/statuses', App\Http\Controllers\Libraries\StatusController::class);
        Route::resource('/libraries/positions', App\Http\Controllers\Libraries\PositionController::class);
        Route::resource('/libraries/salaries', App\Http\Controllers\Libraries\SalaryController::class);
        
        Route::patch('/libraries/products/{id}/toggle-active', [App\Http\Controllers\Libraries\ProductController::class, 'toggleActive']);
        Route::patch('/libraries/positions/{id}/toggle-active', [App\Http\Controllers\Libraries\PositionController::class, 'toggleActive']);
        Route::get('/inventory', [App\Http\Controllers\InventoryManagementController::class, 'index']);
        
        Route::get('/purchase-orders/next-po-number', [App\Http\Controllers\PurchaseOrderController::class, 'getNextPoNumber']);
        Route::resource('/purchase-orders', App\Http\Controllers\PurchaseOrderController::class);
        Route::put('/purchase-orders/{id}/status', [App\Http\Controllers\PurchaseOrderController::class, 'updateStatus']);
        Route::get('/purchase-orders/{id}/print', [App\Http\Controllers\PurchaseOrderController::class, 'printPO']);
        Route::get('/received-stocks/next-batch-code', [App\Http\Controllers\ReceivedStockController::class, 'getNextBatchCode']);
        Route::resource('/received-stocks', App\Http\Controllers\ReceivedStockController::class);
        Route::resource('inventory-stocks', App\Http\Controllers\InventoryStockController::class);
        Route::post('inventory-stocks/adjustment/{id}', [App\Http\Controllers\InventoryAdjustmentController::class, 'store']);
        Route::post('/inventory-stocks/{id}/update-price', [App\Http\Controllers\InventoryStockController::class, 'update']);
        
        Route::get('/receipts', [App\Http\Controllers\Libraries\ReceiptController::class, 'index']);
        Route::get('/receipts/{id}/print', [App\Http\Controllers\Libraries\ReceiptController::class, 'print']);
        Route::resource('/remittances', App\Http\Controllers\RemittanceController::class);
        Route::post('/remittances/{id}/approve', [App\Http\Controllers\RemittanceController::class, 'approve'])->name('remittances.approve');
        Route::get('/remittances/{id}/print', [App\Http\Controllers\RemittanceController::class, 'printRemittance']);
        
        Route::resource('/payroll-settings', App\Http\Controllers\Modules\PayrollSettingController::class);
        Route::get('/payroll-templates/available-employees', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'getAvailableEmployees']);
        Route::resource('/payroll-templates', App\Http\Controllers\Modules\PayrollTemplateController::class);
        Route::post('/payroll-templates/{templateId}/add-employees', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'addEmployees']);
        Route::delete('/payroll-templates/{templateId}/employees/{employeeId}', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'removeEmployee']);
        Route::resource('/payroll', App\Http\Controllers\Modules\PayrollController::class);
    });
});

require __DIR__.'/auth.php';
