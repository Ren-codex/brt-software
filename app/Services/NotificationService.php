<?php

namespace App\Services;

use App\Models\InventoryStocks;
use App\Models\PettyCashFund;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\OverdueInvoiceNotification;
use App\Notifications\UnpaidSameDaySalesOrderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function markRead(Request $request, string $id): array
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return ['message' => 'Notification marked as read.', 'status' => true];
    }

    public function markAllRead(Request $request): array
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ['message' => 'All notifications marked as read.', 'status' => true];
    }

    public function checkAndNotifyLowBalance(PettyCashFund $fund, float $previousBalance, float $newBalance): void
    {
        if (
            $fund->low_balance_threshold !== null
            && $previousBalance >= (float) $fund->low_balance_threshold
            && $newBalance < (float) $fund->low_balance_threshold
        ) {
            $fundId = $fund->id;
            DB::afterCommit(function () use ($fundId) {
                $notifyFund = PettyCashFund::find($fundId);
                if ($notifyFund) {
                    User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Administrator', 'Top Management']))
                        ->each(fn ($u) => $u->notify(new LowBalanceFundNotification($notifyFund)));
                }
            });
        }
    }

    public function checkAndNotifyLowStock(int $productId, int $previousTotal): void
    {
        DB::afterCommit(function () use ($productId, $previousTotal) {
            $product = Product::with(['brand', 'unit'])->find($productId);
            if (! $product || $product->minimum_stock === null) {
                return;
            }

            $newTotal = (int) InventoryStocks::whereHas(
                'receivedItem', fn ($q) => $q->where('product_id', $productId)
            )->sum('quantity');

            if ($previousTotal >= $product->minimum_stock && $newTotal < $product->minimum_stock) {
                User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Administrator', 'Top Management']))
                    ->each(fn ($u) => $u->notify(new LowStockNotification($product, $newTotal)));
            }
        });
    }

    public function notifyOverdueInvoices(Collection $invoices): void
    {
        if ($invoices->isEmpty()) {
            return;
        }

        $users = User::whereHas(
            'roles', fn ($q) => $q->whereIn('name', ['Administrator', 'Top Management'])
        )->get();

        foreach ($invoices as $invoice) {
            foreach ($users as $user) {
                $user->notify(new OverdueInvoiceNotification($invoice));
            }
        }
    }

    /**
     * COD/credit sales orders placed today that are still unpaid this afternoon —
     * notify the assigned sales rep plus admins so it can be chased down same-day.
     */
    public function notifyUnpaidSameDaySalesOrders(Collection $salesOrders): void
    {
        if ($salesOrders->isEmpty()) {
            return;
        }

        $admins = User::whereHas(
            'roles', fn ($q) => $q->whereIn('name', ['Administrator', 'Top Management'])
        )->get();

        foreach ($salesOrders as $salesOrder) {
            $balanceDue = (float) $salesOrder->arInvoices->sum('balance_due');
            if ($balanceDue <= 0) {
                continue;
            }

            // concat() (not push()) so $admins itself isn't mutated across iterations.
            $recipients = $admins->concat([$salesOrder->salesRep?->user])->filter()->unique('id');

            foreach ($recipients as $user) {
                $user->notify(new UnpaidSameDaySalesOrderNotification($salesOrder, $balanceDue));
            }
        }
    }
}
