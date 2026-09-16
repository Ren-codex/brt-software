<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use Illuminate\Validation\ValidationException;

/**
 * Every bank account owns one ledger account, and the bank's gl_code is that
 * account's code.
 *
 * Cash Management reads a bank's balance off the account whose code equals the
 * bank's gl_code, and posting (JournalEntryService::ensureAccount) finds it by
 * the slug bank_<code>. When the code was typed by hand it usually matched
 * nothing: every bank showed ₱0 and "No GL", and an opening balance had no
 * account to be posted to. So the code is assigned here and the account is
 * made in the same step.
 */
class BankLedgerService
{
    /** The block of the chart set aside for bank accounts. */
    public const FIRST_CODE = 1020;

    public const LAST_CODE = 1049;

    /**
     * The lowest code in the bank block that neither the chart nor another bank
     * is using. The unique indexes on accounts.code and bank_accounts.gl_code
     * are the backstop if two banks are added at the same instant.
     */
    public function nextGlCode(): string
    {
        $taken = Account::pluck('code')
            ->merge(BankAccount::pluck('gl_code'))
            ->map(fn ($code) => (string) $code)
            ->flip();

        for ($code = self::FIRST_CODE; $code <= self::LAST_CODE; $code++) {
            if (!isset($taken[(string) $code])) {
                return (string) $code;
            }
        }

        throw ValidationException::withMessages([
            'bank_name' => 'Every bank GL code from ' . self::FIRST_CODE . ' to ' . self::LAST_CODE
                . ' is in use. Deactivate or remove an unused bank ledger account first.',
        ]);
    }

    public function ledgerName(BankAccount $bank): string
    {
        return $bank->bank_name . ' — ' . $bank->account_name;
    }

    /**
     * The bank's ledger account, created if it is missing and kept named after
     * the bank.
     *
     * Looked up the same way ensureAccount() does, so the first real posting
     * lands in this account rather than creating a second one.
     */
    public function syncLedgerAccount(BankAccount $bank): Account
    {
        $slug = 'bank_' . $bank->gl_code;

        $account = Account::where('slug', $slug)->first()
            ?? Account::where('code', $bank->gl_code)->first();

        if (!$account) {
            return Account::create([
                'code'      => $bank->gl_code,
                'slug'      => $slug,
                'name'      => $this->ledgerName($bank),
                'type'      => 'asset',
                'subtype'   => 'current_asset',
                'is_active' => true,
            ]);
        }

        // Rename only the bank's own account. A code typed before this existed
        // may point at an unrelated account, and renaming that would mislabel it.
        if ($account->slug === $slug && $account->name !== $this->ledgerName($bank)) {
            $account->update(['name' => $this->ledgerName($bank)]);
        }

        return $account;
    }
}
