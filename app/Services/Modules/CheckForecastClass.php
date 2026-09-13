<?php

namespace App\Services\Modules;

use App\Models\BankAccount;
use App\Models\Check;
use App\Services\Accounting\CashManagementService;

/**
 * Answers one question: will a check I have already written bounce?
 *
 * Built from the register rather than the ledger, deliberately. Every check
 * payment posts to one shared "Cash in Bank" account regardless of which bank it
 * was drawn on, so the ledger cannot say which account a check will drain — but
 * the register keeps `bank_account_id`, so it can.
 *
 * Counts only checks still pending. Cleared money has already moved and is in
 * the balance; bounced money never arrived. Nothing here counts an invoice
 * someone hopes will be paid: a projection that over-promises is worse than
 * none, because it is trusted.
 */
class CheckForecastClass
{
    public function __construct(private CashManagementService $cash) {}

    public function build(?int $bankAccountId = null): array
    {
        $accounts = BankAccount::query()
            ->when($bankAccountId, fn ($q) => $q->where('id', $bankAccountId))
            ->orderBy('bank_name')
            ->get();

        $pending = Check::query()
            ->where('status', Check::STATUS_PENDING)
            ->whereNotNull('bank_account_id')
            ->get()
            ->groupBy('bank_account_id');

        return [
            'accounts' => $accounts->map(fn ($account) => $this->project(
                $account,
                $pending->get($account->id, collect())
            ))->values()->all(),

            // A received check nobody has assigned to a bank yet cannot be
            // assumed to land in any particular one, so it is left out of every
            // projection — and surfaced here, because silently dropping money
            // from a forecast is how a forecast starts lying.
            'unassigned_received' => round((float) Check::query()
                ->where('status', Check::STATUS_PENDING)
                ->where('direction', Check::DIRECTION_RECEIVED)
                ->whereNull('bank_account_id')
                ->sum('amount'), 2),
        ];
    }

    private function project(BankAccount $account, $checks): array
    {
        $opening = $this->cash->getBankAccountBalance($account->id);
        $balance = $opening;
        $rows = [];

        $byDate = $checks
            ->sortBy(fn ($check) => $check->check_date->toDateString())
            ->groupBy(fn ($check) => $check->check_date->toDateString());

        foreach ($byDate as $date => $due) {
            $in = round((float) $due->where('direction', Check::DIRECTION_RECEIVED)->sum('amount'), 2);
            $out = round((float) $due->where('direction', Check::DIRECTION_ISSUED)->sum('amount'), 2);
            $balance = round($balance + $in - $out, 2);

            $rows[] = [
                'date' => $date,
                'in' => $in,
                'out' => $out,
                'balance' => $balance,
                'short' => $balance < 0,
                'checks' => $due->map(fn ($c) => [
                    'id' => $c->id,
                    'direction' => $c->direction,
                    'check_number' => $c->check_number,
                    'amount' => (float) $c->amount,
                ])->values()->all(),
            ];
        }

        return [
            'bank_account_id' => $account->id,
            'bank_name' => $account->bank_name,
            'account_name' => $account->account_name,
            'opening_balance' => $opening,
            'rows' => $rows,
            // The worst point ahead, which is the number worth acting on.
            'lowest_balance' => $rows ? min(array_column($rows, 'balance')) : $opening,
            'shortfall_date' => collect($rows)->firstWhere('short', true)['date'] ?? null,
        ];
    }
}
