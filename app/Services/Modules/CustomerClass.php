<?php

namespace App\Services\Modules;


use App\Models\Customer;
use App\Models\SalesOrder;
use App\Http\Resources\Modules\CustomerResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class CustomerClass
{
    public function lists($request){
        $data = CustomerResource::collection(
            Customer::when($request->keyword, function ($query,$keyword) {
                    $query->where(function($q) use ($keyword) {
                        $q->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('address', 'LIKE', "%{$keyword}%")
                          ->orWhere('contact_number', 'LIKE', "%{$keyword}%")
                          ->orWhere('email', 'LIKE', "%{$keyword}%");
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request){
        $data = Customer::create([
            'name' => $request->name,
            'address' => $request->address,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'is_active' => $request->is_active,
            'is_regular' => $request->is_regular,
            'is_blacklisted' => $request->is_blacklisted,
            'added_by_id' => auth()->id(),
        ]);

        return [
            'data' => new CustomerResource($data),
            'message' => 'Customer saved successfully!',
            'info' => "You've successfully saved the customer"
        ];
    }

    public function update($request){
        $data = Customer::findOrFail($request->id);
        $data->update([
            'name' => $request->name,
            'address' => $request->address,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'is_active' => $request->is_active,
            'is_regular' => $request->is_regular,
            'is_blacklisted' => $request->is_blacklisted,
        ]);

        return [
            'data' => new CustomerResource($data),
            'message' => 'Customer updated successfully!',
            'info' => "You've successfully updated the customer"
        ];
    }

    public function toggleActive($request){
        $data = Customer::findOrFail($request->id);
        $data->update([
            'is_active' => $request->is_active,
        ]);

        return [
            'data' => new CustomerResource($data),
            'message' => 'Customer status updated successfully!',
            'info' => "You've successfully updated the customer status"
        ];
    }

    public function delete($id){
        $data = Customer::findOrFail($id);
        $data->delete();

        return [
            'data' => $data,
            'message' => 'Customer deleted was successful!',
            'info' => "You've successfully deleted the customer"
        ];
    }

    public function orderSummary($customerId, $request)
    {
        Customer::findOrFail($customerId);

        $year = (int) ($request->input('year') ?: now()->year);
        $month = (int) ($request->input('month') ?: now()->month);
        $month = max(1, min(12, $month));

        $periodDate = Carbon::create($year, $month, 1);
        $previousPeriodDate = $periodDate->copy()->subMonth();

        $current = $this->buildPeriodSummary($customerId, $periodDate->year, $periodDate->month);
        $previous = $this->buildPeriodSummary($customerId, $previousPeriodDate->year, $previousPeriodDate->month);

        $orderTrend = $this->calculateTrend($current['total_orders'], $previous['total_orders']);
        $riceTrend = $this->calculateTrend($current['total_rice_ordered'], $previous['total_rice_ordered']);
        $amountTrend = $this->calculateTrend($current['total_amount'], $previous['total_amount']);

        return [
            'year' => $year,
            'month' => $month,
            'total_orders' => $current['total_orders'],
            'total_rice_ordered' => $current['total_rice_ordered'],
            'total_amount' => $current['total_amount'],
            'order_trend' => $orderTrend,
            'rice_trend' => $riceTrend,
            'amount_trend' => $amountTrend,
        ];
    }

    public function purchaseHistory($customerId, $request)
    {
        Customer::findOrFail($customerId);

        $count = (int) $request->input('count', 10);
        $count = max(1, min($count, 50));
        $year = (int) ($request->input('year') ?: now()->year);
        $month = (int) ($request->input('month') ?: now()->month);
        $month = max(1, min(12, $month));

        return SalesOrder::with(['status', 'items.product'])
            ->where('customer_id', $customerId)
            ->whereYear('order_date', $year)
            ->whereMonth('order_date', $month)
            ->whereDoesntHave('status', function ($statusQuery) {
                $statusQuery->where('slug', 'cancelled');
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate($count)
            ->appends([
                'count' => $count,
                'year' => $year,
                'month' => $month,
            ])
            ->through(function ($order) {
                $totalItems = (int) $order->items->sum('quantity');
                $totalKg = (float) $order->items->sum(function ($item) {
                    return (float) ($item->quantity ?? 0) * (float) ($item->product->pack_size ?? 0);
                });

                return [
                    'id' => (int) $order->id,
                    'so_number' => $order->so_number,
                    'order_date' => optional($order->order_date)->format('Y-m-d'),
                    'status' => $order->status ? $order->status->name : '-',
                    'payment_mode' => $order->payment_mode ?: '-',
                    'total_items' => $totalItems,
                    'total_kg' => round($totalKg, 2),
                    'total_amount' => round((float) $order->total_amount, 2),
                ];
            });
    }

    private function buildPeriodSummary($customerId, $year, $month)
    {
        $query = SalesOrder::query()
            ->where('customer_id', $customerId)
            ->whereYear('order_date', $year)
            ->whereMonth('order_date', $month)
            ->whereDoesntHave('status', function ($statusQuery) {
                $statusQuery->where('slug', 'cancelled');
            });

        $totalOrders = (int) $query->count();
        $totalAmount = (float) $query->sum('total_amount');
        $totalRiceOrdered = (float) $query->leftJoin('sales_order_items', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->leftJoin('products', 'products.id', '=', 'sales_order_items.product_id')
            ->sum(DB::raw('COALESCE(sales_order_items.quantity, 0) * COALESCE(products.pack_size, 0)'));

        return [
            'total_orders' => $totalOrders,
            'total_rice_ordered' => round($totalRiceOrdered, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    private function calculateTrend($current, $previous)
    {
        if ((float) $previous === 0.0) {
            if ((float) $current === 0.0) {
                return '0%';
            }
            return '100%';
        }

        $percentage = (($current - $previous) / $previous) * 100;
        $rounded = round($percentage, 2);
        $formatted = rtrim(rtrim(number_format(abs($rounded), 2, '.', ''), '0'), '.');

        if ($rounded > 0) {
            return '+' . $formatted . '%';
        }
        if ($rounded < 0) {
            return '-' . $formatted . '%';
        }
        return '0%';
    }



}
