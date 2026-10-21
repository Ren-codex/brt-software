<?php

namespace Database\Seeders;

use App\Services\DropdownClass;
use App\Services\SeriesService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Local test data only: two remittances waiting on verification, so they show
 * on the Approvals page and can be opened with Review. Each one carries a paid
 * sales order, its invoice, and the receipts being remitted.
 *
 * Not called from DatabaseSeeder. Run it by hand:
 *   php artisan db:seed --class=RemittanceApprovalDemoSeeder
 * Each run adds two more.
 */
class RemittanceApprovalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('RemittanceApprovalDemoSeeder only runs with APP_ENV=local.');

            return;
        }

        $status = DB::table('list_statuses')->pluck('id', 'slug');
        foreach (['closed', 'paid', 'for-verification'] as $slug) {
            if (! isset($status[$slug])) {
                $this->command?->error("The '{$slug}' status is missing from list_statuses.");

                return;
            }
        }

        $customers = DB::table('customers')->orderBy('id')->limit(2)->pluck('id');
        if ($customers->isEmpty()) {
            $this->command?->error('Seed some customers first.');

            return;
        }

        $reps = collect(app(DropdownClass::class)->sales_reps())->pluck('value');
        $fallbackUserId = DB::table('users')->orderBy('id')->value('id');

        $batches = [
            ['customer' => $customers[0], 'rep' => $reps[0] ?? null, 'receipts' => [['Cash', 3500], ['GCash', 1200]]],
            ['customer' => $customers[1] ?? $customers[0], 'rep' => $reps[1] ?? $reps[0] ?? null, 'receipts' => [['Cash', 2000], ['Check', 5000]]],
        ];

        DB::transaction(function () use ($batches, $status, $fallbackUserId) {
            foreach ($batches as $batch) {
                $tag = 'DEMO-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
                $total = collect($batch['receipts'])->sum(fn ($r) => $r[1]);
                // The rep's own account submits the remittance when there is one.
                $createdById = ($batch['rep'] ? DB::table('employees')->where('id', $batch['rep'])->value('user_id') : null)
                    ?? $fallbackUserId;

                $orderId = DB::table('sales_orders')->insertGetId([
                    'so_number' => "SO-{$tag}",
                    'payment_mode' => 'Cash',
                    'order_date' => now()->toDateString(),
                    'total_amount' => $total,
                    'total_discount' => 0,
                    'customer_id' => $batch['customer'],
                    'sales_rep_id' => $batch['rep'],
                    'status_id' => $status['closed'],
                    'added_by_id' => $createdById,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $invoiceId = DB::table('ar_invoices')->insertGetId([
                    'sales_order_id' => $orderId,
                    'status_id' => $status['paid'],
                    'invoice_number' => "INV-{$tag}",
                    'invoice_date' => now()->toDateString(),
                    'amount_due' => $total,
                    'amount_paid' => $total,
                    'balance_due' => 0,
                    'total_discount' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $remittanceId = DB::table('remittances')->insertGetId([
                    'remittance_no' => app(SeriesService::class)->get('remittance'),
                    'remittance_date' => now()->toDateString(),
                    // Same shape the Create Remittance modal sends.
                    'summary' => json_encode(['cash_sales' => $total, 'credit_sales' => 0, 'bank_transfer' => 0]),
                    'total_amount' => $total,
                    'status_id' => $status['for-verification'],
                    'created_by_id' => $createdById,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($batch['receipts'] as $i => [$mode, $amount]) {
                    DB::table('receipts')->insert([
                        'ar_invoice_id' => $invoiceId,
                        'customer_id' => $batch['customer'],
                        'status_id' => $status['for-verification'],
                        'receipt_number' => "OR-{$tag}-".($i + 1),
                        'receipt_type' => 'payment',
                        'remittance_id' => $remittanceId,
                        'receipt_date' => now()->toDateString(),
                        'amount_paid' => $amount,
                        'balance_due' => 0,
                        'payment_mode' => $mode,
                        'reference_number' => $mode === 'Cash' ? null : "REF-{$tag}-".($i + 1),
                        'bank_name' => $mode === 'Check' ? 'BDO' : null,
                        'check_date' => $mode === 'Check' ? now()->toDateString() : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->command?->info("Remittance #{$remittanceId} for verification: ₱".number_format($total, 2));
            }
        });
    }
}
