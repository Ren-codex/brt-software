<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Services\Accounting\JournalEntryService;
use App\Services\SeriesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GeneralExpenseController extends Controller
{
    public function __construct(
        private JournalEntryService $journal,
        private SeriesService $series,
    ) {}

    public function index()
    {
        $expenses = Expense::with(['glAccount', 'bankAccount', 'added_by', 'approvedBy', 'releasedBy'])
            ->whereNull('fund_id')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($e) => $this->format($e));

        $expenseAccounts = Account::where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);

        $bankAccounts = BankAccount::where('is_active', true)
            ->orderBy('bank_name')
            ->get(['id', 'bank_name', 'account_name']);

        return inertia('Modules/Accounting/Expenses', [
            'expenses'        => $expenses,
            'expenseAccounts' => $expenseAccounts,
            'bankAccounts'    => $bankAccounts,
            'paymentMethods'  => [
                ['value' => 'cash',          'label' => 'Cash'],
                ['value' => 'check',         'label' => 'Check'],
                ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_date'    => 'required|date',
            'payee'           => 'required|string|max:120',
            'gl_account_id'   => 'required|integer|exists:accounts,id',
            'payment_method'  => 'required|in:cash,check,bank_transfer',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'reference_no'    => 'nullable|string|max:60',
            'description'     => 'nullable|string|max:500',
            'receipt'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts/general-expenses', 'public');
        }

        $expense = Expense::create([
            'expense_date'    => $data['expense_date'],
            'payee'           => $data['payee'],
            'gl_account_id'   => $data['gl_account_id'],
            'payment_method'  => $data['payment_method'],
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'amount'          => $data['amount'],
            'reference_no'    => $data['reference_no'] ?? null,
            'description'     => $data['description'] ?? null,
            'receipt_path'    => $receiptPath,
            'status'          => 'draft',
            'added_by_id'     => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Expense recorded.',
            'data'    => $this->format($expense->fresh(['glAccount', 'bankAccount', 'added_by'])),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $expense = Expense::whereNull('fund_id')->findOrFail($id);

        if (!in_array($expense->status, ['draft', 'submitted'])) {
            return response()->json(['message' => 'Only draft or submitted expenses can be edited.'], 422);
        }

        $data = $request->validate([
            'expense_date'    => 'required|date',
            'payee'           => 'required|string|max:120',
            'gl_account_id'   => 'required|integer|exists:accounts,id',
            'payment_method'  => 'required|in:cash,check,bank_transfer',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'reference_no'    => 'nullable|string|max:60',
            'description'     => 'nullable|string|max:500',
            'receipt'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($request->hasFile('receipt')) {
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $data['receipt_path'] = $request->file('receipt')->store('receipts/general-expenses', 'public');
        }

        $expense->update([
            'expense_date'    => $data['expense_date'],
            'payee'           => $data['payee'],
            'gl_account_id'   => $data['gl_account_id'],
            'payment_method'  => $data['payment_method'],
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'amount'          => $data['amount'],
            'reference_no'    => $data['reference_no'] ?? null,
            'description'     => $data['description'] ?? null,
            'receipt_path'    => $data['receipt_path'] ?? $expense->receipt_path,
        ]);

        return response()->json([
            'message' => 'Expense updated.',
            'data'    => $this->format($expense->fresh(['glAccount', 'bankAccount', 'added_by'])),
        ]);
    }

    public function approve(int $id)
    {
        $expense = Expense::whereNull('fund_id')->findOrFail($id);

        if ($expense->status !== 'draft') {
            return response()->json(['message' => 'Only draft expenses can be approved.'], 422);
        }

        $expense->update([
            'status'         => 'approved',
            'approved_by_id' => Auth::id(),
            'approved_at'    => now(),
        ]);

        $this->journal->recordGeneralExpenseEntry($expense->fresh(['glAccount', 'bankAccount']));

        return response()->json([
            'message' => 'Expense approved. Journal entry posted.',
            'data'    => $this->format($expense->fresh(['glAccount', 'bankAccount', 'added_by', 'approvedBy'])),
        ]);
    }

    public function void(int $id)
    {
        $expense = Expense::whereNull('fund_id')->findOrFail($id);

        if ($expense->status === 'voided') {
            return response()->json(['message' => 'Expense is already voided.'], 422);
        }

        if ($expense->status === 'approved') {
            $this->journal->reverseEntriesForSource($expense, 'General expense voided.', now()->toDateString());
        }

        $expense->update(['status' => 'voided']);

        return response()->json([
            'message' => 'Expense voided.',
            'data'    => $this->format($expense->fresh()),
        ]);
    }

    public function destroy(int $id)
    {
        $expense = Expense::whereNull('fund_id')->findOrFail($id);

        if ($expense->status !== 'draft') {
            return response()->json(['message' => 'Only draft expenses can be deleted.'], 422);
        }

        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    private function format(Expense $e): array
    {
        return [
            'id'              => $e->id,
            'expense_date'    => $e->expense_date,
            'payee'           => $e->payee,
            'gl_account_id'   => $e->gl_account_id,
            'gl_account_code' => optional($e->glAccount)->code,
            'gl_account_name' => optional($e->glAccount)->name,
            'payment_method'  => $e->payment_method,
            'bank_account_id' => $e->bank_account_id,
            'bank_account'    => $e->bank_account_id
                ? (optional($e->bankAccount)->bank_name . ' — ' . optional($e->bankAccount)->account_name)
                : null,
            'amount'          => (float) $e->amount,
            'amount_fmt'      => '₱' . number_format($e->amount, 2),
            'reference_no'    => $e->reference_no,
            'description'     => $e->description,
            'receipt_path'    => $e->receipt_path ? Storage::url($e->receipt_path) : null,
            'status'          => $e->status,
            'added_by'        => optional($e->added_by)->username,
            'approved_by'     => optional($e->approvedBy)->username,
            'released_by'     => optional($e->releasedBy)->username,
            'submitted_at'    => $e->submitted_at?->toDateString(),
            'approved_at'     => $e->approved_at?->toDateString(),
            'released_at'     => $e->released_at?->toDateString(),
        ];
    }
}
