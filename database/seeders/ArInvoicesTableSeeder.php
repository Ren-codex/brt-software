<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ArInvoice;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\User;
use App\Models\ListStatus;

class ArInvoicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed if we have the required related data
        if (SalesOrder::count() > 0 && Customer::count() > 0 && User::count() > 0) {
            // Get credit sales orders that don't have AR invoices yet
            $creditOrders = SalesOrder::where('payment_mode', '!=', 'Cash')
                                     ->whereDoesntHave('arInvoice')
                                     ->take(5)
                                     ->get();

            foreach ($creditOrders as $order) {
                // Calculate totals from order items
                $subtotal = 0;
                $discountAmount = 0;

                foreach ($order->items as $item) {
                    $unitCost = $item->unit_cost ?? 0;
                    $quantity = $item->quantity ?? 0;
                    $discount = $item->discount_per_unit ?? 0;

                    $itemTotal = ($unitCost - $discount) * $quantity;
                    $subtotal += $itemTotal;
                    $discountAmount += $discount * $quantity;
                }

                $taxAmount = $subtotal * 0.12; // 12% VAT
                $totalAmount = $subtotal + $taxAmount - $discountAmount;

                ArInvoice::create([
                    'ar_number' => ArInvoice::generateArNumber($order->created_at),
                    'invoice_date' => $order->order_date,
                    'due_date' => $order->order_date->copy()->addDays(30), // 30 days payment term
                    'sales_order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discountAmount,
                    'total_amount' => $totalAmount,
                    'notes' => 'Auto-generated AR invoice for sales order ' . $order->so_number,
                    'issued_by_id' => $order->added_by_id ?? User::first()->id,
                    'status_id' => ListStatus::where('name', 'Unpaid')->first()->id ?? 1,
                ]);
            }
        }
    }
}
