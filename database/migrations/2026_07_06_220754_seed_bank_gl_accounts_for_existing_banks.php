<?php

use App\Models\Account;
use App\Models\BankAccount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Creates a dedicated ledger account for every existing bank account, using
     * its own gl_code, so it appears by name (e.g. "BDO — BRT") in the Manual
     * Journal Entry account picker instead of only the generic "Cash in Bank"
     * line. Previously these were only ever created lazily the first time a
     * transaction touched that bank, via JournalEntryService::resolveBankAccountGl().
     * Structural only — no monetary balances.
     */
    public function up(): void
    {
        foreach (BankAccount::all() as $bank) {
            Account::firstOrCreate(
                ['code' => $bank->gl_code],
                [
                    'slug'      => 'bank_' . $bank->gl_code,
                    'name'      => $bank->bank_name . ' — ' . $bank->account_name,
                    'type'      => 'asset',
                    'subtype'   => 'current_asset',
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Account::whereIn('code', BankAccount::pluck('gl_code'))
            ->whereDoesntHave('journalEntryLines')
            ->delete();
    }
};
