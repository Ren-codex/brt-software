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
    Route::get('/reports', [App\Http\Controllers\Modules\ReportsController::class, 'index']);
    Route::post('/sales-orders/{id}/adjustment', [App\Http\Controllers\Modules\SalesOrderController::class, 'adjustment']);
    Route::resource('/sales-orders-external', App\Http\Controllers\Modules\SalesOrderExternalController::class);
    Route::post('/sales-orders-external/{id}/adjustment', [App\Http\Controllers\Modules\SalesOrderExternalController::class, 'adjustment']);
    Route::resource('/ar-invoices', App\Http\Controllers\Modules\ArInvoiceController::class);
    Route::resource('/employees', App\Http\Controllers\Modules\EmployeeController::class);
    Route::get('/employees/{id}/incentives-summary', [App\Http\Controllers\Modules\EmployeeController::class, 'incentivesSummary']);
    Route::resource('/customers', App\Http\Controllers\Modules\CustomerController::class);
    Route::get('/customers/{id}/details', [App\Http\Controllers\Modules\CustomerController::class, 'details']);
    Route::get('/customers/{id}/order-summary', [App\Http\Controllers\Modules\CustomerController::class, 'orderSummary']);
    Route::get('/customers/{id}/purchase-history', [App\Http\Controllers\Modules\CustomerController::class, 'purchaseHistory']);
     Route::resource('/suppliers', App\Http\Controllers\Libraries\SupplierController::class);
    Route::patch('/suppliers/{id}/toggle-active', [App\Http\Controllers\Libraries\SupplierController::class, 'toggleActive']);
    Route::patch('/suppliers/{id}/toggle-blacklist', [App\Http\Controllers\Libraries\SupplierController::class, 'toggleBlacklist']);
    Route::get('/suppliers/{id}/purchase-order-summary', [App\Http\Controllers\Libraries\SupplierController::class, 'purchaseOrderSummary']);
    Route::get('/suppliers/{id}/stock-return-summary', [App\Http\Controllers\Libraries\SupplierController::class, 'stockReturnSummary']);
    Route::get('/suppliers/{id}/stock-returns', [App\Http\Controllers\Libraries\SupplierController::class, 'stockReturns']);
    Route::resource('/receipts', App\Http\Controllers\Modules\ReceiptController::class);

    // Make revenue reports available to all authenticated users (not just administrators)
    Route::get('/api/revenue-reports', [App\Http\Controllers\Modules\RevenueReportController::class, 'index']);

    Route::middleware(['role:Administrator,Top Management,Area Business Manager,Super Admin'])->group(function () {
        Route::patch('/expenses/{id}/approve', [App\Http\Controllers\Modules\ExpenseController::class, 'approve']);
        Route::patch('/expenses/{id}/release', [App\Http\Controllers\Modules\ExpenseController::class, 'release']);
    });

    Route::middleware(['role:Administrator'])->group(function () {
        Route::resource('/users', App\Http\Controllers\System\UserController::class);
        // Route::resource('/libraries/suppliers', App\Http\Controllers\Libraries\SupplierController::class);
        Route::resource('/libraries/roles', App\Http\Controllers\Libraries\RoleController::class);
        Route::resource('/libraries/brands', App\Http\Controllers\Libraries\BrandController::class);
        Route::resource('/libraries/units', App\Http\Controllers\Libraries\UnitController::class);
        Route::resource('/libraries/products', App\Http\Controllers\Libraries\ProductController::class);
        Route::resource('/libraries/statuses', App\Http\Controllers\Libraries\StatusController::class);
        Route::resource('/libraries/positions', App\Http\Controllers\Libraries\PositionController::class);
        Route::resource('/libraries/salaries', App\Http\Controllers\Libraries\SalaryController::class);
        Route::resource('/libraries/locations', App\Http\Controllers\Libraries\LocationController::class);
        Route::resource('/libraries/payroll-items', App\Http\Controllers\Libraries\PayrollItemController::class);

        Route::patch('/libraries/products/{id}/toggle-active', [App\Http\Controllers\Libraries\ProductController::class, 'toggleActive']);
        Route::patch('/libraries/positions/{id}/toggle-active', [App\Http\Controllers\Libraries\PositionController::class, 'toggleActive']);
        Route::patch('/libraries/payroll-items/{id}/toggle-active', [App\Http\Controllers\Libraries\PayrollItemController::class, 'toggleActive']);
    });

    Route::middleware(['role:Administrator,Warehouse Manager'])->group(function () {
        Route::get('/inventory', [App\Http\Controllers\InventoryManagementController::class, 'index']);
        Route::get('/purchase-orders/next-po-number', [App\Http\Controllers\PurchaseOrderController::class, 'getNextPoNumber']);
        Route::resource('/purchase-orders', App\Http\Controllers\PurchaseOrderController::class);
        Route::put('/purchase-orders/{id}/status', [App\Http\Controllers\PurchaseOrderController::class, 'updateStatus']);
        Route::resource('/stock-returns', App\Http\Controllers\StockReturnController::class);
        Route::post('/stock-returns/{id}/approve', [App\Http\Controllers\StockReturnController::class, 'approve']);
        Route::post('/stock-returns/{id}/items/{itemId}/receive', [App\Http\Controllers\StockReturnController::class, 'receiveItem']);
        Route::post('/stock-returns/{id}/log-supplier-delivery', [App\Http\Controllers\StockReturnController::class, 'logSupplierDelivery']);
        Route::get('/purchase-orders/{id}/print', [App\Http\Controllers\PurchaseOrderController::class, 'printPO']);
        Route::get('/received-stocks/next-batch-code', [App\Http\Controllers\ReceivedStockController::class, 'getNextBatchCode']);
        Route::post('/received-stocks/{receivedStock}/pay', [App\Http\Controllers\ReceivedStockController::class, 'pay']);
        Route::resource('/received-stocks', App\Http\Controllers\ReceivedStockController::class);
        Route::resource('inventory-stocks', App\Http\Controllers\InventoryStockController::class);
        Route::post('inventory-stocks/adjustment/{id}', [App\Http\Controllers\InventoryAdjustmentController::class, 'store']);
        Route::post('/inventory-stocks/{id}/update-price', [App\Http\Controllers\InventoryStockController::class, 'update']);
    });

    Route::middleware(['role:Administrator'])->group(function () {
        // Route::get('/receipts', [App\Http\Controllers\Libraries\ReceiptController::class, 'index']);
        // Route::get('/receipts/{id}/print', [App\Http\Controllers\Libraries\ReceiptController::class, 'print']);
        Route::resource('/remittances', App\Http\Controllers\RemittanceController::class);
        Route::post('/remittances/{id}/approve', [App\Http\Controllers\RemittanceController::class, 'approve'])->name('remittances.approve');
        Route::get('/remittances/{id}/print', [App\Http\Controllers\RemittanceController::class, 'printRemittance']);
        
        Route::resource('/payroll-settings', App\Http\Controllers\Modules\PayrollSettingController::class);
        Route::get('/payroll-templates/available-employees', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'getAvailableEmployees']);
        Route::resource('/payroll-templates', App\Http\Controllers\Modules\PayrollTemplateController::class);
        Route::post('/payroll-templates/{templateId}/add-employees', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'addEmployees']);
        Route::delete('/payroll-templates/{templateId}/employees/{employeeId}', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'removeEmployee']);
        Route::resource('/payrolls', App\Http\Controllers\Modules\PayrollController::class);
        Route::resource('/loans', App\Http\Controllers\Modules\LoanController::class);
        Route::resource('/loan-payments', App\Http\Controllers\Modules\LoanPaymentController::class);
        Route::get('/accounting', [App\Http\Controllers\Modules\AccountingController::class, 'index']);
        Route::get('/accounting/general-ledger', [App\Http\Controllers\Modules\AccountingController::class, 'generalLedger']);
        Route::get('/accounting/trial-balance', [App\Http\Controllers\Modules\AccountingController::class, 'trialBalance']);
        Route::get('/accounting/profit-loss', [App\Http\Controllers\Modules\AccountingController::class, 'profitLoss']);
        Route::get('/accounting/balance-sheet', [App\Http\Controllers\Modules\AccountingController::class, 'balanceSheet']);
        Route::get('/accounting/accounts-receivable', [App\Http\Controllers\Modules\AccountingController::class, 'accountsReceivable']);
        Route::get('/accounting/accounts-payable', [App\Http\Controllers\Modules\AccountingController::class, 'accountsPayable']);
        Route::get('/accounting/chart-of-accounts', [App\Http\Controllers\Modules\AccountingController::class, 'chartOfAccounts']);
        Route::get('/accounting/journal-entries', [App\Http\Controllers\Modules\AccountingController::class, 'journalEntries']);
        Route::resource('/expenses', App\Http\Controllers\Modules\ExpenseController::class);
        Route::get('/payrolls/{id}/print', [App\Http\Controllers\Modules\PayrollController::class, 'printPayroll']);
        Route::get('/sales-incentives', [App\Http\Controllers\Modules\SalesIncentivesController::class, 'index']);
        Route::put('/payrolls/{id}/status', [App\Http\Controllers\Modules\PayrollController::class, 'updateStatus']);
        Route::put('/loans/{id}/status', [App\Http\Controllers\Modules\LoanController::class, 'updateStatus']);
        
        // Contact Management
        Route::resource('/contacts', App\Http\Controllers\Modules\ContactController::class);
        Route::patch('/contacts/{id}/mark-read', [App\Http\Controllers\Modules\ContactController::class, 'markAsRead']);
    });
});

// Public route for submitting contact form from landing page
Route::post('/api/contacts', [App\Http\Controllers\Modules\ContactController::class, 'store']);

require __DIR__.'/auth.php';
