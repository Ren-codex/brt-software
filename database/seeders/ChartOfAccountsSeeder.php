<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Baseline structural Chart of Accounts — codes and names only, no
     * monetary balances (balances are always derived live from posted
     * journal entries). Mirrors the accounts created by the
     * seed_baseline_chart_of_accounts migration, kept here too so a fresh
     * `php artisan db:seed` sets up the same reference data.
     */
    public function run(): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'slug' => 'cash', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'current_asset'],
            ['code' => '1011', 'slug' => 'cash_in_bank', 'name' => 'Cash in Bank', 'type' => 'asset', 'subtype' => 'cash'],
            ['code' => '1090', 'slug' => 'card_clearing', 'name' => 'Card Clearing', 'type' => 'asset', 'subtype' => 'current_asset'],
            ['code' => '1100', 'slug' => 'accounts_receivable', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'current_asset'],
            ['code' => '1150', 'slug' => 'allowance_doubtful_accounts', 'name' => 'Allowance for Doubtful Accounts', 'type' => 'asset', 'subtype' => 'contra_asset'],
            ['code' => '1160', 'slug' => 'employee_loans_receivable', 'name' => 'Employee Loans Receivable', 'type' => 'asset', 'subtype' => 'current_asset'],
            ['code' => '1200', 'slug' => 'rice_inventory', 'name' => 'Rice Inventory', 'type' => 'asset', 'subtype' => 'inventory'],
            ['code' => '1300', 'slug' => 'prepaid_expenses', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'subtype' => 'current_asset'],
            ['code' => '1500', 'slug' => 'property_and_equipment', 'name' => 'Property and Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset'],
            ['code' => '1510', 'slug' => 'accumulated_depreciation', 'name' => 'Accumulated Depreciation', 'type' => 'asset', 'subtype' => 'contra_asset'],

            // Liabilities
            ['code' => '2000', 'slug' => 'accounts_payable', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'current_liability'],
            ['code' => '2100', 'slug' => 'payroll_deductions_payable', 'name' => 'Payroll Deductions Payable', 'type' => 'liability', 'subtype' => 'current_liability'],
            ['code' => '2110', 'slug' => 'sss_philhealth_pagibig_payable', 'name' => 'SSS / PhilHealth / Pag-IBIG Payable', 'type' => 'liability', 'subtype' => 'current_liability'],
            ['code' => '2120', 'slug' => 'withholding_tax_payable', 'name' => 'Withholding Tax Payable', 'type' => 'liability', 'subtype' => 'current_liability'],
            ['code' => '2200', 'slug' => 'accrued_expenses_payable', 'name' => 'Accrued Expenses Payable', 'type' => 'liability', 'subtype' => 'current_liability'],

            // Equity
            ['code' => '3000', 'slug' => 'owners_capital', 'name' => "Owner's Capital", 'type' => 'equity', 'subtype' => 'capital'],
            ['code' => '3100', 'slug' => 'owners_drawing', 'name' => "Owner's Drawing", 'type' => 'equity', 'subtype' => 'drawing'],
            ['code' => '3900', 'slug' => 'opening-balance-equity', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'subtype' => 'opening_balance'],

            // Revenue
            ['code' => '4100', 'slug' => 'rice_sales', 'name' => 'Rice Sales', 'type' => 'revenue', 'subtype' => 'sales'],
            ['code' => '4110', 'slug' => 'sales_returns_allowances', 'name' => 'Sales Returns And Allowances', 'type' => 'revenue', 'subtype' => 'contra_revenue'],
            ['code' => '4200', 'slug' => 'inventory_adjustment_gain', 'name' => 'Inventory Adjustment Gain', 'type' => 'revenue', 'subtype' => 'other_income'],
            ['code' => '4300', 'slug' => 'other_income', 'name' => 'Other Income', 'type' => 'revenue', 'subtype' => 'other_income'],

            // Cost of Sales
            ['code' => '5100', 'slug' => 'cost_of_goods_sold', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'subtype' => 'cost_of_sales'],

            // Inventory Variance
            ['code' => '5200', 'slug' => 'inventory_adjustment_loss', 'name' => 'Inventory Adjustment Loss', 'type' => 'expense', 'subtype' => 'inventory_variance'],
            ['code' => '5201', 'slug' => 'weight_loss_expense', 'name' => 'Weight Loss Expense', 'type' => 'expense', 'subtype' => 'inventory_variance'],
            ['code' => '5202', 'slug' => 'conversion_variance', 'name' => 'Inventory Conversion Variance', 'type' => 'expense', 'subtype' => 'inventory_variance'],

            // Operating Expenses
            ['code' => '5300', 'slug' => 'utilities_expense', 'name' => 'Utilities Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5310', 'slug' => 'salaries_wages_expense', 'name' => 'Salaries and Wages Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5311', 'slug' => 'supplies_expense', 'name' => 'Supplies Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5320', 'slug' => 'transportation_expense', 'name' => 'Transportation Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5330', 'slug' => 'employer_contributions_expense', 'name' => 'SSS / PhilHealth / Pag-IBIG Employer Contribution Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5340', 'slug' => 'rent_expense', 'name' => 'Rent Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5341', 'slug' => 'maintenance_expense', 'name' => 'Maintenance Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5350', 'slug' => 'insurance_expense', 'name' => 'Insurance Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5360', 'slug' => 'taxes_licenses_expense', 'name' => 'Taxes and Licenses Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5370', 'slug' => 'operational_expense', 'name' => 'Operational Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5380', 'slug' => 'freight_delivery_expense', 'name' => 'Freight and Delivery Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5390', 'slug' => 'other_expense', 'name' => 'Other Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5400', 'slug' => 'loss_on_damaged_goods', 'name' => 'Loss on Damaged Goods', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5410', 'slug' => 'bank_charges_expense', 'name' => 'Bank Charges Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5420', 'slug' => 'depreciation_expense', 'name' => 'Depreciation Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '5900', 'slug' => 'cash_over_short', 'name' => 'Cash Over/Short', 'type' => 'expense', 'subtype' => 'operating_expense'],
        ];

        foreach ($accounts as $account) {
            Account::firstOrCreate(
                ['slug' => $account['slug']],
                $account + ['is_active' => true]
            );
        }
    }
}
