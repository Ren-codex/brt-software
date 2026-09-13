<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfills the checks register from existing receipts and supplier
 * payments so the register holds a row for every check that already
 * existed before this feature, not just ones registered from here on.
 *
 * Inserts via the DB facade rather than CheckRegisterClass, deliberately
 * bypassing the service layer (and its Task 9 duplicate-check-number
 * guard): historic data may well contain duplicate check numbers, and this
 * migration must not choke on them — it is a faithful copy of what already
 * happened, not a place to enforce a rule that didn't exist at the time.
 *
 * Issued checks are inserted as already `cleared`: they posted under the
 * old behaviour before this feature existed, and rewriting posted history
 * is out of scope. Received checks preserve their real pending/cleared
 * split via `confirmed_at`, since an unconfirmed receipt genuinely has not
 * cleared yet.
 *
 * Historic rows predate the check columns, so there is no real check date
 * or number to backfill from — payment_date/receipt_date and
 * reference_number are the best available stand-ins. `checks.check_number`
 * is NOT NULL, so a missing reference_number becomes the literal string
 * '(unknown)': the row still exists and is visibly incomplete, rather than
 * silently absent from the register.
 *
 * Guarded by exists() checks against source_type/source_id so this is safe
 * to re-run — this will eventually run against a live production database,
 * and a half-applied production migration must be repeatable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (DB::table('receipts')->where('payment_mode', 'Check')->get() as $r) {
            if (DB::table('checks')->where('source_type', 'App\Models\Receipt')->where('source_id', $r->id)->exists()) {
                continue;
            }
            $repId = DB::table('ar_invoices as i')
                ->join('sales_orders as so', 'i.sales_order_id', '=', 'so.id')
                ->where('i.id', $r->ar_invoice_id)
                ->value('so.sales_rep_id');

            DB::table('checks')->insert([
                'direction' => 'received',
                'check_number' => $r->reference_number ?: '(unknown)',
                'check_date' => $r->check_date ?: $r->receipt_date,
                'amount' => $r->amount_paid,
                'bank_name' => $r->bank_name,
                'status' => $r->confirmed_at ? 'cleared' : 'pending',
                'cleared_at' => $r->confirmed_at,
                'cleared_by_id' => $r->confirmed_by_id,
                'source_type' => 'App\Models\Receipt',
                'source_id' => $r->id,
                'customer_id' => $r->customer_id,
                'received_by_id' => $repId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (DB::table('received_stock_payments')->where('payment_mode', 'Check')->get() as $p) {
            if (DB::table('checks')->where('source_type', 'App\Models\ReceivedStockPayment')->where('source_id', $p->id)->exists()) {
                continue;
            }
            $supplierId = DB::table('received_stocks')->where('id', $p->received_stock_id)->value('supplier_id');

            DB::table('checks')->insert([
                'direction' => 'issued',
                // Historic rows predate the check columns: payment_date and
                // reference_number are the best available stand-ins. Both rows
                // are already cleared, so neither affects the forecast.
                'check_number' => $p->reference_number ?: '(unknown)',
                'check_date' => $p->payment_date,
                'amount' => $p->amount_paid,
                'bank_name' => $p->bank_name,
                'bank_account_id' => $p->bank_account_id,
                'status' => 'cleared',
                'cleared_at' => $p->created_at,
                'source_type' => 'App\Models\ReceivedStockPayment',
                'source_id' => $p->id,
                'supplier_id' => $supplierId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Deliberately does nothing. Backfilled rows carry the same source_type
        // values as rows created through the UI, so there is no way to tell them
        // apart afterwards — a truncate here would destroy every real check
        // alongside the backfilled ones. Removing backfilled data is a manual,
        // deliberate act, not something a rollback should do on your behalf.
    }
};
