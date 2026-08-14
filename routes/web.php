<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [App\Http\Controllers\WelcomeController::class, 'index'])->middleware('guest')->name('welcome');;

// Landing Page Routes
Route::get('/landing', function () {
    return Inertia::render('Landing');
})->middleware('guest')->name('landing');

Route::middleware(['2fa','auth','is_active'])->group(function () {
    Route::get('/', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::resource('/sales-orders', App\Http\Controllers\Modules\SalesOrderController::class);
    Route::get('/reports', [App\Http\Controllers\Modules\ReportsController::class, 'index']);
    Route::post('/sales-orders/{id}/adjustment', [App\Http\Controllers\Modules\SalesOrderController::class, 'adjustment']);
    Route::resource('/sales-orders-external', App\Http\Controllers\Modules\SalesOrderExternalController::class);
    Route::post('/sales-orders-external/{id}/adjustment', [App\Http\Controllers\Modules\SalesOrderExternalController::class, 'adjustment']);
    Route::resource('/ar-invoices', App\Http\Controllers\Modules\ArInvoiceController::class);
    Route::resource('/employees', App\Http\Controllers\Modules\EmployeeController::class)
        ->middlewareFor(['index', 'show'], 'permission:employees,view')
        ->middlewareFor(['store', 'update'], 'permission:employees,encoder')
        ->middlewareFor('destroy', 'permission:employees,admin');
    Route::get('/employees/{id}/incentives-summary', [App\Http\Controllers\Modules\EmployeeController::class, 'incentivesSummary'])
        ->middleware('permission:employees,view');
    Route::resource('/customers', App\Http\Controllers\Modules\CustomerController::class)
        ->middlewareFor(['index', 'show'], 'permission:customers,view')
        ->middlewareFor(['store', 'update'], 'permission:customers,encoder')
        ->middlewareFor('destroy', 'permission:customers,admin');
    Route::get('/customers/{id}/details', [App\Http\Controllers\Modules\CustomerController::class, 'details'])
        ->middleware('permission:customers,view');
    Route::get('/customers/{id}/order-summary', [App\Http\Controllers\Modules\CustomerController::class, 'orderSummary'])
        ->middleware('permission:customers,view');
    Route::get('/customers/{id}/purchase-history', [App\Http\Controllers\Modules\CustomerController::class, 'purchaseHistory'])
        ->middleware('permission:customers,view');
     Route::resource('/suppliers', App\Http\Controllers\Libraries\SupplierController::class);
    Route::patch('/suppliers/{id}/toggle-active', [App\Http\Controllers\Libraries\SupplierController::class, 'toggleActive']);
    Route::patch('/suppliers/{id}/toggle-blacklist', [App\Http\Controllers\Libraries\SupplierController::class, 'toggleBlacklist']);
    Route::get('/suppliers/{id}/purchase-order-summary', [App\Http\Controllers\Libraries\SupplierController::class, 'purchaseOrderSummary']);
    Route::get('/suppliers/{id}/stock-return-summary', [App\Http\Controllers\Libraries\SupplierController::class, 'stockReturnSummary']);
    Route::get('/suppliers/{id}/stock-returns', [App\Http\Controllers\Libraries\SupplierController::class, 'stockReturns']);
    Route::resource('/receipts', App\Http\Controllers\Modules\ReceiptController::class);

    // Make revenue reports available to all authenticated users (not just administrators)
    Route::get('/api/revenue-reports', [App\Http\Controllers\Modules\RevenueReportController::class, 'index']);

    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::patch('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead']);

    Route::middleware(['role:Administrator,Top Management,Area Business Manager,Super Admin'])->group(function () {
        // Replenishment request workflow
        Route::get('/replenishments', [App\Http\Controllers\Modules\ReplenishmentController::class, 'index']);
        Route::post('/replenishments', [App\Http\Controllers\Modules\ReplenishmentController::class, 'store']);
        Route::get('/replenishments/{id}', [App\Http\Controllers\Modules\ReplenishmentController::class, 'show']);
        Route::patch('/replenishments/{id}/submit', [App\Http\Controllers\Modules\ReplenishmentController::class, 'submit']);
        Route::patch('/replenishments/{id}/approve', [App\Http\Controllers\Modules\ReplenishmentController::class, 'approve']);
        Route::patch('/replenishments/{id}/reject', [App\Http\Controllers\Modules\ReplenishmentController::class, 'reject']);
    });

    Route::middleware(['role:Administrator'])->group(function () {
        Route::resource('/users', App\Http\Controllers\System\UserController::class)
            ->middlewareFor(['index', 'show'], 'permission:user_management,view')
            ->middlewareFor(['store', 'update'], 'permission:user_management,encoder');
        // Route::resource('/libraries/suppliers', App\Http\Controllers\Libraries\SupplierController::class);
        Route::resource('/libraries/roles', App\Http\Controllers\Libraries\RoleController::class)
            ->middlewareFor(['index', 'show'], 'permission:libraries,roles,view')
            ->middlewareFor(['store', 'update'], 'permission:libraries,roles,encoder')
            ->middlewareFor('destroy', 'permission:libraries,roles,admin');
        Route::get('/libraries/roles/{id}/permissions', [App\Http\Controllers\Libraries\RolePermissionController::class, 'show'])
            ->middleware('permission:libraries,roles,view');
        Route::post('/libraries/roles/{id}/permissions', [App\Http\Controllers\Libraries\RolePermissionController::class, 'update'])
            ->middleware('permission:libraries,roles,admin');
        Route::resource('/libraries/brands', App\Http\Controllers\Libraries\BrandController::class)
            ->middlewareFor(['index', 'show'], 'permission:libraries,brands,view')
            ->middlewareFor(['store', 'update'], 'permission:libraries,brands,encoder')
            ->middlewareFor('destroy', 'permission:libraries,brands,admin');
        Route::resource('/libraries/units', App\Http\Controllers\Libraries\UnitController::class)
            ->middlewareFor(['index', 'show'], 'permission:libraries,units,view')
            ->middlewareFor(['store', 'update'], 'permission:libraries,units,encoder')
            ->middlewareFor('destroy', 'permission:libraries,units,admin');
        Route::resource('/libraries/products', App\Http\Controllers\Libraries\ProductController::class)
            ->middlewareFor(['index', 'show'], 'permission:libraries,products,view')
            ->middlewareFor(['store', 'update'], 'permission:libraries,products,encoder')
            ->middlewareFor('destroy', 'permission:libraries,products,admin');
        Route::resource('/libraries/statuses', App\Http\Controllers\Libraries\StatusController::class)
            ->middlewareFor(['index', 'show'], 'permission:libraries,statuses,view')
            ->middlewareFor(['store', 'update'], 'permission:libraries,statuses,encoder')
            ->middlewareFor('destroy', 'permission:libraries,statuses,admin');
        Route::resource('/libraries/positions', App\Http\Controllers\Libraries\PositionController::class)
            ->middlewareFor(['index', 'show'], 'permission:employees,view')
            ->middlewareFor(['store', 'update'], 'permission:employees,encoder')
            ->middlewareFor('destroy', 'permission:employees,admin');
        Route::resource('/libraries/salaries', App\Http\Controllers\Libraries\SalaryController::class)
            ->middlewareFor(['index', 'show'], 'permission:employees,view')
            ->middlewareFor(['store', 'update'], 'permission:employees,encoder')
            ->middlewareFor('destroy', 'permission:employees,admin');
        Route::resource('/libraries/locations', App\Http\Controllers\Libraries\LocationController::class)
            ->middlewareFor(['index', 'show'], 'permission:libraries,locations,view')
            ->middlewareFor(['store', 'update'], 'permission:libraries,locations,encoder')
            ->middlewareFor('destroy', 'permission:libraries,locations,admin');
        Route::resource('/libraries/payroll-items', App\Http\Controllers\Libraries\PayrollItemController::class)
            ->middlewareFor('index', 'permission:payroll,payroll_settings,view')
            ->middlewareFor(['store', 'update'], 'permission:payroll,payroll_settings,encoder')
            ->middlewareFor('destroy', 'permission:payroll,payroll_settings,admin');
        Route::resource('/libraries/packagings', App\Http\Controllers\Libraries\PackagingController::class)
            ->middlewareFor(['index', 'show'], 'permission:libraries,packagings,view')
            ->middlewareFor(['store', 'update'], 'permission:libraries,packagings,encoder')
            ->middlewareFor('destroy', 'permission:libraries,packagings,admin');

        Route::patch('/libraries/products/{id}/toggle-active', [App\Http\Controllers\Libraries\ProductController::class, 'toggleActive'])
            ->middleware('permission:libraries,products,encoder');
        Route::patch('/libraries/positions/{id}/toggle-active', [App\Http\Controllers\Libraries\PositionController::class, 'toggleActive'])
            ->middleware('permission:employees,encoder');
        Route::patch('/libraries/payroll-items/{id}/toggle-active', [App\Http\Controllers\Libraries\PayrollItemController::class, 'toggleActive'])
            ->middleware('permission:payroll,payroll_settings,encoder');

        Route::resource('/accounting/funds', App\Http\Controllers\Libraries\FundController::class)->only(['index', 'store', 'update']);
        Route::post('/accounting/funds/{id}/top-up', [App\Http\Controllers\Libraries\FundController::class, 'topUp']);
        Route::patch('/accounting/funds/{id}/balance', [App\Http\Controllers\Libraries\FundController::class, 'adjustBalance']);
        Route::patch('/accounting/funds/{id}/toggle-active', [App\Http\Controllers\Libraries\FundController::class, 'toggleActive']);
    });

    Route::middleware(['role:Administrator,Warehouse Manager'])->group(function () {
        Route::get('/inventory', [App\Http\Controllers\InventoryManagementController::class, 'index']);
        Route::get('/purchase-orders/next-po-number', [App\Http\Controllers\PurchaseOrderController::class, 'getNextPoNumber'])
            ->middleware('permission:inventory,purchase_orders,encoder');
        Route::resource('/purchase-orders', App\Http\Controllers\PurchaseOrderController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory,purchase_orders,view')
            ->middlewareFor(['store', 'update'], 'permission:inventory,purchase_orders,encoder')
            ->middlewareFor('destroy', 'permission:inventory,purchase_orders,admin');
        Route::put('/purchase-orders/{id}/status', [App\Http\Controllers\PurchaseOrderController::class, 'updateStatus'])
            ->middleware('permission:inventory,purchase_orders,approver');
        Route::patch('/purchase-orders/{id}/void', [App\Http\Controllers\PurchaseOrderController::class, 'void'])
            ->middleware('permission:inventory,purchase_orders,approver');
        Route::resource('/stock-returns', App\Http\Controllers\StockReturnController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory,stock_returns,view')
            ->middlewareFor('store', 'permission:inventory,stock_returns,encoder');
        Route::post('/stock-returns/{id}/approve', [App\Http\Controllers\StockReturnController::class, 'approve'])
            ->middleware('permission:inventory,stock_returns,approver');
        Route::post('/stock-returns/{id}/items/{itemId}/receive', [App\Http\Controllers\StockReturnController::class, 'receiveItem'])
            ->middleware('permission:inventory,stock_returns,approver');
        Route::get('/purchase-orders/{id}/print', [App\Http\Controllers\PurchaseOrderController::class, 'printPO'])
            ->middleware('permission:inventory,purchase_orders,view');
        Route::get('/received-stocks/next-batch-code', [App\Http\Controllers\ReceivedStockController::class, 'getNextBatchCode'])
            ->middleware('permission:inventory,receiving,encoder');
        Route::get('/accounting/cash-on-hand', [App\Http\Controllers\Modules\CashManagementController::class, 'cashOnHand']);
        Route::post('/received-stocks/{receivedStock}/pay', [App\Http\Controllers\ReceivedStockController::class, 'pay'])
            ->middleware('permission:inventory,receiving,encoder');
        Route::resource('/received-stocks', App\Http\Controllers\ReceivedStockController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory,receiving,view')
            ->middlewareFor(['store', 'update'], 'permission:inventory,receiving,encoder')
            ->middlewareFor('destroy', 'permission:inventory,receiving,admin');
        Route::resource('inventory-stocks', App\Http\Controllers\InventoryStockController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory,inventory_stocks,view')
            ->middlewareFor('update', 'permission:inventory,inventory_stocks,encoder');
        Route::post('inventory-stocks/adjustment/{id}', [App\Http\Controllers\InventoryAdjustmentController::class, 'store'])
            ->middleware('permission:inventory,inventory_stocks,encoder');
        Route::post('/inventory-stocks/{id}/update-price', [App\Http\Controllers\InventoryStockController::class, 'update'])
            ->middleware('permission:inventory,inventory_stocks,encoder');
        Route::post('/inventory-stocks/conversions', [App\Http\Controllers\ProductConversionController::class, 'store'])
            ->middleware('permission:inventory,inventory_stocks,encoder');
        Route::post('/inventory-stocks/{id}/weight-loss', [App\Http\Controllers\WeightLossController::class, 'store'])
            ->middleware('permission:inventory,inventory_stocks,encoder');
        Route::post('/inventory-stocks/{id}/settings', [App\Http\Controllers\InventoryStockController::class, 'settings'])
            ->middleware('permission:inventory,inventory_stocks,admin');
    });

    Route::middleware(['role:Administrator,Super Admin'])->group(function () {
        Route::patch('/app-settings/{key}', [App\Http\Controllers\AppSettingController::class, 'update']);
    });

    Route::middleware(['role:Administrator'])->group(function () {
        // Route::get('/receipts', [App\Http\Controllers\Libraries\ReceiptController::class, 'index']);
        // Route::get('/receipts/{id}/print', [App\Http\Controllers\Libraries\ReceiptController::class, 'print']);
        // only(): the controller implements no create/edit/update, so the full
        // resource registered three routes that threw on any request.
        Route::resource('/remittances', App\Http\Controllers\RemittanceController::class)
            ->only(['index', 'store', 'show', 'destroy']);
        Route::post('/remittances/{id}/approve', [App\Http\Controllers\RemittanceController::class, 'approve'])->name('remittances.approve');
        Route::get('/remittances/{id}/print', [App\Http\Controllers\RemittanceController::class, 'printRemittance']);
        Route::post('/remittances/{id}/remit', [App\Http\Controllers\RemittanceController::class, 'remit'])->name('remittances.remit');
        
        Route::resource('/payroll-settings', App\Http\Controllers\Modules\PayrollSettingController::class)
            ->middlewareFor('index', 'permission:payroll,payroll_settings,view')
            ->middlewareFor('update', 'permission:payroll,payroll_settings,encoder');
        Route::get('/payroll-templates/available-employees', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'getAvailableEmployees'])
            ->middleware('permission:payroll,payroll_templates,view');
        Route::resource('/payroll-templates', App\Http\Controllers\Modules\PayrollTemplateController::class)
            ->middlewareFor('index', 'permission:payroll,payroll_templates,view')
            ->middlewareFor(['store', 'update'], 'permission:payroll,payroll_templates,encoder')
            ->middlewareFor('destroy', 'permission:payroll,payroll_templates,admin');
        Route::post('/payroll-templates/{templateId}/add-employees', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'addEmployees'])
            ->middleware('permission:payroll,payroll_templates,encoder');
        Route::delete('/payroll-templates/{templateId}/employees/{employeeId}', [App\Http\Controllers\Modules\PayrollTemplateController::class, 'removeEmployee'])
            ->middleware('permission:payroll,payroll_templates,encoder');
        Route::resource('/payrolls', App\Http\Controllers\Modules\PayrollController::class)
            ->middlewareFor(['index', 'show'], 'permission:payroll,payroll_processing,view')
            ->middlewareFor(['store', 'update'], 'permission:payroll,payroll_processing,encoder')
            ->middlewareFor('destroy', 'permission:payroll,payroll_processing,admin');
        Route::resource('/loans', App\Http\Controllers\Modules\LoanController::class)
            ->middlewareFor('index', 'permission:payroll,loans,view')
            ->middlewareFor(['store', 'update'], 'permission:payroll,loans,encoder')
            ->middlewareFor('destroy', 'permission:payroll,loans,admin');
        Route::resource('/loan-payments', App\Http\Controllers\Modules\LoanPaymentController::class)
            ->middlewareFor('index', 'permission:payroll,loans,view')
            ->middlewareFor(['store', 'update'], 'permission:payroll,loans,encoder')
            ->middlewareFor('destroy', 'permission:payroll,loans,admin');
        Route::get('/accounting', [App\Http\Controllers\Modules\AccountingController::class, 'index'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/general-ledger', [App\Http\Controllers\Modules\AccountingController::class, 'generalLedger'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/trial-balance', [App\Http\Controllers\Modules\AccountingController::class, 'trialBalance'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/profit-loss', [App\Http\Controllers\Modules\AccountingController::class, 'profitLoss'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/balance-sheet', [App\Http\Controllers\Modules\AccountingController::class, 'balanceSheet'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/cash-flow', [App\Http\Controllers\Modules\AccountingController::class, 'cashFlowStatement'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/accounts-receivable', [App\Http\Controllers\Modules\AccountingController::class, 'accountsReceivable'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/accounts-payable', [App\Http\Controllers\Modules\AccountingController::class, 'accountsPayable'])
            ->middleware('permission:accounting,financial_reports,view');
        Route::get('/accounting/settings', [App\Http\Controllers\Modules\AccountingController::class, 'settings'])
            ->middleware('permission:accounting,chart_of_accounts,view');
        Route::get('/accounting/chart-of-accounts', fn() => redirect('/accounting/settings'));
        Route::get('/accounting/bank-accounts', fn() => redirect('/accounting/settings'));
        Route::post('/accounting/accounts', [App\Http\Controllers\Modules\AccountingController::class, 'storeAccount'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
        Route::put('/accounting/accounts/{id}', [App\Http\Controllers\Modules\AccountingController::class, 'updateAccount'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
        Route::patch('/accounting/accounts/{id}/toggle', [App\Http\Controllers\Modules\AccountingController::class, 'toggleAccount'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
        Route::delete('/accounting/accounts/{id}', [App\Http\Controllers\Modules\AccountingController::class, 'destroyAccount'])
            ->middleware('permission:accounting,chart_of_accounts,admin');
        Route::get('/accounting/journal-entries', [App\Http\Controllers\Modules\AccountingController::class, 'journalEntries'])
            ->middleware('permission:accounting,journal_entries,view');
        Route::post('/accounting/journal-entries', [App\Http\Controllers\Modules\AccountingController::class, 'storeManualJournal'])
            ->middleware('permission:accounting,journal_entries,encoder');
        Route::get('/accounting/cash-management', [App\Http\Controllers\Modules\CashManagementController::class, 'index'])
            ->middleware('permission:accounting,cash_management,view');
        Route::get('/accounting/petty-cash', [App\Http\Controllers\Modules\PettyCashController::class, 'index']);
        Route::post('/accounting/petty-cash/vouchers', [App\Http\Controllers\Modules\PettyCashController::class, 'storeVoucher']);
        Route::delete('/accounting/petty-cash/vouchers/{id}', [App\Http\Controllers\Modules\PettyCashController::class, 'voidVoucher']);
        Route::get('/accounting/expenses', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'index']);
        Route::post('/accounting/expenses', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'store']);
        Route::put('/accounting/expenses/{id}', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'update']);
        Route::patch('/accounting/expenses/{id}/approve', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'approve']);
        Route::patch('/accounting/expenses/{id}/void', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'void']);
        Route::delete('/accounting/expenses/{id}', [App\Http\Controllers\Modules\GeneralExpenseController::class, 'destroy']);
        Route::get('/accounting/bank-reconciliation', [App\Http\Controllers\Modules\BankReconciliationController::class, 'index']);
        Route::post('/accounting/bank-reconciliation', [App\Http\Controllers\Modules\BankReconciliationController::class, 'start']);
        Route::get('/accounting/bank-reconciliation/{id}', [App\Http\Controllers\Modules\BankReconciliationController::class, 'show']);
        Route::post('/accounting/bank-reconciliation/{id}/toggle-clear', [App\Http\Controllers\Modules\BankReconciliationController::class, 'toggleClear']);
        Route::post('/accounting/bank-reconciliation/{id}/finalize', [App\Http\Controllers\Modules\BankReconciliationController::class, 'finalize']);
        Route::delete('/accounting/bank-reconciliation/{id}', [App\Http\Controllers\Modules\BankReconciliationController::class, 'destroy']);
        Route::post('/accounting/fund-transfers', [App\Http\Controllers\Modules\CashManagementController::class, 'storeFundTransfer'])
            ->middleware('permission:accounting,cash_management,encoder');
        Route::delete('/accounting/fund-transfers/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyFundTransfer'])
            ->middleware('permission:accounting,cash_management,admin');
        Route::post('/accounting/petty-cash/transactions', [App\Http\Controllers\Modules\CashManagementController::class, 'storePettyCashTransaction'])
            ->middleware('permission:accounting,petty_cash,encoder');
        Route::delete('/accounting/petty-cash/transactions/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyPettyCashTransaction'])
            ->middleware('permission:accounting,petty_cash,admin');
        Route::post('/accounting/bank-deposits', [App\Http\Controllers\Modules\CashManagementController::class, 'storeDeposit'])
            ->middleware('permission:accounting,cash_management,encoder');
        Route::delete('/accounting/bank-deposits/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyDeposit'])
            ->middleware('permission:accounting,cash_management,admin');
        Route::post('/accounting/bank-withdrawals', [App\Http\Controllers\Modules\CashManagementController::class, 'storeWithdrawal'])
            ->middleware('permission:accounting,cash_management,encoder');
        Route::delete('/accounting/bank-withdrawals/{id}', [App\Http\Controllers\Modules\CashManagementController::class, 'destroyWithdrawal'])
            ->middleware('permission:accounting,cash_management,admin');
        Route::get('/accounting/bank-accounts/list', [App\Http\Controllers\Modules\BankAccountController::class, 'list'])
            ->middleware('permission:accounting,chart_of_accounts,view');
        Route::post('/accounting/bank-accounts', [App\Http\Controllers\Modules\BankAccountController::class, 'store'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
        Route::put('/accounting/bank-accounts/{id}', [App\Http\Controllers\Modules\BankAccountController::class, 'update'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');
        Route::patch('/accounting/bank-accounts/{id}/toggle', [App\Http\Controllers\Modules\BankAccountController::class, 'toggle'])
            ->middleware('permission:accounting,chart_of_accounts,encoder');

        Route::get('/payrolls/{id}/print', [App\Http\Controllers\Modules\PayrollController::class, 'printPayroll'])
            ->middleware('permission:payroll,payroll_processing,view');
        Route::get('/sales-incentives', [App\Http\Controllers\Modules\SalesIncentivesController::class, 'index']);
        Route::put('/payrolls/{id}/status', [App\Http\Controllers\Modules\PayrollController::class, 'updateStatus'])
            ->middleware('permission:payroll,payroll_processing,approver');
        Route::put('/loans/{id}/status', [App\Http\Controllers\Modules\LoanController::class, 'updateStatus'])
            ->middleware('permission:payroll,loans,approver');
        
        // Contact Management
        Route::resource('/contacts', App\Http\Controllers\Modules\ContactController::class);
        Route::patch('/contacts/{id}/mark-read', [App\Http\Controllers\Modules\ContactController::class, 'markAsRead']);
    });
});

// Public route for submitting contact form from landing page
Route::post('/api/contacts', [App\Http\Controllers\Modules\ContactController::class, 'store']);

require __DIR__.'/auth.php';
