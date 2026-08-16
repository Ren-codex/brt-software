<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Services\Accounting\CashManagementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankAccountController extends Controller
{
    /**
     * GL codes hardcoded in JournalEntryService::ensureAccount() calls for
     * system-generated accounts (Cash, Cash in Bank, Card Clearing, Accounts
     * Receivable/Payable, revenue/expense accounts, etc.). A bank account must
     * never reuse one of these — ensureAccount() falls back to matching by
     * code when the slug isn't found, so a collision would silently post a
     * bank's transactions into the wrong system account.
     */
    private const RESERVED_SYSTEM_CODES = [
        '1000', '1011', '1012', '1050', '1090', '1100', '1200',
        '2000', '2100',
        '4100', '4110', '4200',
        '5100', '5200', '5201', '5202', '5300', '5310', '5311', '5320', '5341', '5370', '5390', '5400', '5900',
    ];

    public function __construct(private CashManagementService $cashManagementService) {}

    private function glCodeRules(?int $ignoreBankAccountId = null, ?string $currentGlCode = null): array
    {
        return [
            'required',
            'string',
            'max:20',
            Rule::unique('bank_accounts', 'gl_code')->ignore($ignoreBankAccountId),
            function ($attribute, $value, $fail) use ($currentGlCode) {
                if ($value === $currentGlCode) {
                    return;
                }
                if (in_array($value, self::RESERVED_SYSTEM_CODES, true) || Account::where('code', $value)->exists()) {
                    $fail('This GL code is reserved for an existing or system ledger account. Choose a different code.');
                }
            },
        ];
    }

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
            'gl_code'        => $this->glCodeRules(),
        ]);

        $data['created_by_id'] = auth()->id();

        BankAccount::create($data);

        return response()->json(['message' => 'Bank account created.']);
    }

    public function update(Request $request, int $id)
    {
        $account = BankAccount::findOrFail($id);

        $data = $request->validate([
            'bank_name'      => 'required|string|max:100',
            'account_name'   => 'required|string|max:150',
            'account_number' => 'nullable|string|max:50',
            'gl_code'        => $this->glCodeRules($id, $account->gl_code),
        ]);

        $account->update($data);

        return response()->json(['message' => 'Bank account updated.']);
    }

    public function toggle(int $id)
    {
        $account = BankAccount::findOrFail($id);
        $account->update(['is_active' => !$account->is_active]);

        return response()->json(['message' => $account->is_active ? 'Bank account activated.' : 'Bank account deactivated.']);
    }

    public function list()
    {
        $accounts = BankAccount::active()->orderBy('bank_name')->orderBy('account_name')
            ->get(['id', 'bank_name', 'account_name', 'account_number', 'gl_code']);

        $accounts->each(function ($account) {
            $account->balance = $this->cashManagementService->getBankAccountBalance($account->id);
            $account->balance_formatted = '₱' . number_format($account->balance, 2);
        });

        return response()->json($accounts);
    }
}
