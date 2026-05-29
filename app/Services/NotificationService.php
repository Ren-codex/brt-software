<?php

namespace App\Services;

use App\Models\InventoryStocks;
use App\Models\PettyCashFund;
use App\Models\Product;
use App\Models\User;
use App\Notifications\LowBalanceFundNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\OverdueInvoiceNotification;
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
}
