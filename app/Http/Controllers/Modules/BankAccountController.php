<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Services\Accounting\BankLedgerService;
use App\Services\Accounting\CashManagementService;
use App\Services\System\Permission\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankAccountController extends Controller
{
    public function __construct(
        private CashManagementService $cashManagementService,
        private BankLedgerService $ledger,
    ) {}

    /** Only what a person names. The GL code is assigned, never typed. */
    private function detailRules(): array
    {
        return [
            'bank_name'      => 'required|string|max:100',
            'account_name'   => 'required|string|max:150',
            'account_number' => 'nullable|string|max:50',
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
        // A gl_code in the request is ignored: validated() drops it. Letting
        // people type one is how six banks ended up pointing at codes the chart
        // had never heard of.
        $data = $request->validate($this->detailRules());

        $bank = DB::transaction(function () use ($data) {
            $bank = BankAccount::create($data + [
                'gl_code'       => $this->ledger->nextGlCode(),
                'created_by_id' => auth()->id(),
            ]);

            $this->ledger->syncLedgerAccount($bank);

            return $bank;
        });

        return response()->json([
            'message' => "Bank account created with GL code {$bank->gl_code}.",
            'gl_code' => $bank->gl_code,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $account = BankAccount::findOrFail($id);

        // The code is fixed once assigned: moving it would strand every entry
        // already posted under the old one.
        $data = $request->validate($this->detailRules());

        DB::transaction(function () use ($account, $data) {
            $account->update($data);
            $this->ledger->syncLedgerAccount($account);
        });

        return response()->json(['message' => 'Bank account updated.']);
    }

    public function toggle(int $id)
    {
        $account = BankAccount::findOrFail($id);
        $account->update(['is_active' => !$account->is_active]);

        return response()->json(['message' => $account->is_active ? 'Bank account activated.' : 'Bank account deactivated.']);
    }

    /**
     * The bank accounts a payment screen can offer as a destination.
     *
     * Separate from list() because that one is accounting's own view — it hands
     * back account numbers, GL codes and every balance, and is gated on
     * accounting access. Choosing where a payment lands is an operational act
     * that happens in Inventory and Sales too, so a warehouse manager settling a
     * supplier bill or a sales rep collecting a customer transfer needs this
     * without being handed the run of the chart of accounts.
     */
    public function paymentOptions(PermissionService $permissions)
    {
        $user = auth()->user();

        // Money going out: the payer needs to see what each account holds.
        $canDisburse = $permissions->userHasAccess($user, 'inventory', 'receiving', 'encoder')
            || $permissions->userHasAccess($user, 'accounting', 'chart_of_accounts', 'view');
        // Money coming in: only needs to name the account it arrived in.
        $canCollect = $permissions->userHasAccess($user, 'sales', 'sales_orders', 'encoder')
            || $permissions->userHasAccess($user, 'sales', 'ar_invoices', 'encoder');

        if (!$canDisburse && !$canCollect) {
            abort(403, 'You do not have permission to record payments.');
        }

        $accounts = BankAccount::active()->orderBy('bank_name')->orderBy('account_name')
            ->get(['id', 'bank_name', 'account_name']);

        // A balance only matters when it caps what can be spent, so it is not
        // sent to someone who can only collect.
        if ($canDisburse) {
            $accounts->each(function ($account) {
                $account->balance = $this->cashManagementService->getBankAccountBalance($account->id);
                $account->balance_formatted = '₱' . number_format($account->balance, 2);
            });
        }

        return response()->json($accounts);
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
