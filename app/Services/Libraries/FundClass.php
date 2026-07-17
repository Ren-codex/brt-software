<?php

namespace App\Services\Libraries;

use App\Http\Resources\Libraries\FundResource;
use App\Models\PettyCashFund;
use App\Services\Accounting\CashManagementService;
use Illuminate\Validation\ValidationException;

class FundClass
{
    public function __construct(protected CashManagementService $cashManagement) {}

    public function lists($request)
    {
        return FundResource::collection(
            PettyCashFund::when($request->keyword, fn ($q, $kw) => $q->where('name', 'LIKE', "%{$kw}%"))
                ->orderBy('name')
                ->paginate($request->count ?? 15)
        );
    }

    public function save($request, $userId = null)
    {
        $data = $this->cashManagement->createFund([
            'name' => $request->name,
            'gl_code' => $request->gl_code,
            'initial_balance' => $request->initial_balance ?? null,
            'bank_account_id' => $request->bank_account_id ?? null,
            'low_balance_threshold' => $request->low_balance_threshold ?? null,
        ]);

        return [
            'data' => new FundResource($data),
            'message' => 'Fund created successfully!',
            'info' => "Petty cash fund '{$data->name}' has been created.",
        ];
    }

    public function update($request)
    {
        $data = PettyCashFund::findOrFail($request->id);
        $data->update([
            'name' => $request->name,
            'gl_code' => $request->gl_code,
            'low_balance_threshold' => $request->low_balance_threshold ?? null,
        ]);

        return [
            'data' => new FundResource($data->fresh()),
            'message' => 'Fund updated successfully!',
            'info' => "Petty cash fund '{$data->name}' has been updated.",
        ];
    }

    public function topUp($id, $request)
    {
        $amount = (float) $request->amount;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Top-up amount must be greater than zero.',
            ]);
        }

        $fund = PettyCashFund::findOrFail($id);

        $sourceType = $request->source_type ?? 'cash';
        $bankAccountId = $sourceType === 'bank' ? $request->bank_account_id : null;

        if ($sourceType === 'cash') {
            $cashOnHand = $this->cashManagement->getCashOnHandBalance();
            if ($amount > $cashOnHand) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount exceeds available Cash on Hand (₱' . number_format($cashOnHand, 2) . '). Reduce the amount or fund from a bank account instead.',
                ]);
            }
        } else {
            $bankBalance = $this->cashManagement->getBankAccountBalance((int) $bankAccountId);
            if ($amount > $bankBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount exceeds this bank account\'s available balance (₱' . number_format($bankBalance, 2) . ').',
                ]);
            }
        }

        $this->cashManagement->addTransaction($fund, [
            'type' => 'replenishment',
            'amount' => $amount,
            'transaction_date' => $request->date ?? now()->toDateString(),
            'description' => $request->description ?? null,
            'source_type' => $sourceType,
            'bank_account_id' => $bankAccountId,
        ]);

        return [
            'data' => new FundResource($fund->fresh()),
            'message' => 'Fund topped up successfully!',
            'info' => '₱'.number_format($amount, 2)." added to '{$fund->name}'.",
        ];
    }

    public function adjustBalance($id, $request)
    {
        $fund = PettyCashFund::findOrFail($id);
        $newBalance = (float) $request->balance;

        $txn = $this->cashManagement->adjustFundBalance($fund, $newBalance, $request->reason);

        if (!$txn) {
            return [
                'data' => new FundResource($fund->fresh()),
                'message' => 'No change made.',
                'info' => 'Balance already ₱'.number_format($fund->balance, 2).'.',
            ];
        }

        return [
            'data' => new FundResource($fund->fresh()),
            'message' => 'Balance adjusted successfully!',
            'info' => 'Balance set to ₱'.number_format($fund->fresh()->balance, 2).'.',
        ];
    }

    public function toggleActive($id, bool $isActive)
    {
        $fund = PettyCashFund::findOrFail($id);
        $fund->update(['is_active' => $isActive]);

        return [
            'data' => new FundResource($fund->fresh()),
            'message' => $isActive ? 'Fund activated.' : 'Fund deactivated.',
            'info' => "Fund '{$fund->name}' is now ".($isActive ? 'active' : 'inactive').'.',
        ];
    }
}
