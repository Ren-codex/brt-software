<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index()
    {
        $accounts = BankAccount::orderBy('bank_name')->orderBy('account_name')->get();

        return inertia('Modules/Accounting/BankAccounts', [
            'accounts'     => $accounts,
            'summaryCards' => [
                ['title' => 'Total Banks',    'value' => $accounts->pluck('bank_name')->unique()->count(), 'description' => 'Distinct banks configured.',          'icon' => 'ri-bank-line'],
                ['title' => 'Bank Accounts',  'value' => $accounts->count(),                              'description' => 'Total bank accounts registered.',     'icon' => 'ri-bank-card-line'],
                ['title' => 'Active',         'value' => $accounts->where('is_active', true)->count(),    'description' => 'Accounts available for payments.',     'icon' => 'ri-checkbox-circle-line'],
                ['title' => 'Inactive',       'value' => $accounts->where('is_active', false)->count(),   'description' => 'Accounts disabled from new payments.', 'icon' => 'ri-forbid-line'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_name'      => 'required|string|max:100',
            'account_name'   => 'required|string|max:150',
            'account_number' => 'nullable|string|max:50',
            'gl_code'        => 'required|string|max:20|unique:bank_accounts,gl_code',
        ]);

        $data['created_by_id'] = auth()->id();

        BankAccount::create($data);

        return back()->with('success', 'Bank account created.');
    }

    public function update(Request $request, int $id)
    {
        $account = BankAccount::findOrFail($id);

        $data = $request->validate([
            'bank_name'      => 'required|string|max:100',
            'account_name'   => 'required|string|max:150',
            'account_number' => 'nullable|string|max:50',
            'gl_code'        => 'required|string|max:20|unique:bank_accounts,gl_code,' . $id,
        ]);

        $account->update($data);

        return back()->with('success', 'Bank account updated.');
    }

    public function toggle(int $id)
    {
        $account = BankAccount::findOrFail($id);
        $account->update(['is_active' => !$account->is_active]);

        return back()->with('success', $account->is_active ? 'Bank account activated.' : 'Bank account deactivated.');
    }

    public function list()
    {
        return response()->json(
            BankAccount::active()->orderBy('bank_name')->orderBy('account_name')
                ->get(['id', 'bank_name', 'account_name', 'account_number', 'gl_code'])
        );
    }
}
