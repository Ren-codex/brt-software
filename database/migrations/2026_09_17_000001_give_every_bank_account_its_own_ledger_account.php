<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every existing bank account the ledger account it should always have had.
 *
 * GL codes used to be typed by hand, and most matched nothing in the chart, so
 * Cash Management showed those banks at ₱0 with "No GL". New banks now get a
 * code from the 1020–1049 block and their account at creation
 * (BankLedgerService); this brings the existing ones into line.
 *
 * - A bank whose code already names an account is left alone: that code may
 *   carry posted history.
 * - A bank with an unmatched code inside the block keeps it and gets its account.
 * - A bank with an unmatched code outside the block is re-coded into the block,
 *   in bank-name order. Nothing can be posted under a code with no account, so
 *   there is no history to strand.
 *
 * Deliberately self-contained rather than calling BankLedgerService: a
 * migration that read application code would change meaning whenever that
 * code did.
 */
return new class extends Migration
{
    private const FIRST_CODE = 1020;

    private const LAST_CODE = 1049;

    public function up(): void
    {
        $banks = DB::table('bank_accounts')
            ->orderBy('bank_name')
            ->orderBy('account_name')
            ->orderBy('id')
            ->get();

        foreach ($banks as $bank) {
            $code = (string) $bank->gl_code;

            $mapped = DB::table('accounts')->where('slug', 'bank_' . $code)->exists()
                || DB::table('accounts')->where('code', $code)->exists();

            if ($mapped) {
                continue;
            }

            $inBlock = ctype_digit($code)
                && (int) $code >= self::FIRST_CODE
                && (int) $code <= self::LAST_CODE;

            if (!$inBlock) {
                $code = $this->nextFreeCode();
                DB::table('bank_accounts')->where('id', $bank->id)->update([
                    'gl_code'    => $code,
                    'updated_at' => now(),
                ]);
            }

            DB::table('accounts')->insert([
                'code'       => $code,
                'slug'       => 'bank_' . $code,
                'name'       => $bank->bank_name . ' — ' . $bank->account_name,
                'type'       => 'asset',
                'subtype'    => 'current_asset',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Not reversed. The original codes were the problem, and nothing records
     * which banks were re-coded; restore from the pre-migration backup if needed.
     */
    public function down(): void
    {
    }

    private function nextFreeCode(): string
    {
        $taken = DB::table('accounts')->pluck('code')
            ->merge(DB::table('bank_accounts')->pluck('gl_code'))
            ->map(fn ($code) => (string) $code)
            ->flip();

        for ($code = self::FIRST_CODE; $code <= self::LAST_CODE; $code++) {
            if (!isset($taken[(string) $code])) {
                return (string) $code;
            }
        }

        throw new RuntimeException('No free bank GL code between ' . self::FIRST_CODE . ' and ' . self::LAST_CODE . '.');
    }
};
