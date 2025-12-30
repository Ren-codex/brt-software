<?php

namespace App\Services\Modules;

use Illuminate\Support\Facades\DB;

use App\Models\SalesOrder;
use App\Models\InventoryStocks;
use App\Models\ReceivedItem;
use App\Models\Product;
use App\Models\InventoryAdjustment;
use App\Http\Resources\Modules\SalesOrderResource;
use App\Services\Modules\InventoryService;


class SalesOrderClass
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function lists($request){
        $data = SalesOrderResource::collection(
            SalesOrder::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhereHas('status', function($q) use ($keyword){
                              $q->where('name', 'LIKE', "%{$keyword}%");
                          });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }


    public function save($request){

        // Validate stock availability for all items
        foreach($request->items as $item){
            if (!$this->inventoryService->hasSufficientStock($item['product_id'], $item['quantity'])) {
                $product = Product::find($item['product_id']);
                throw new \Exception('Insufficient stock for product: ' . ($product ? $product->name : 'Unknown Product'));
            }
        }

        $data = new SalesOrder();
        $data->so_number = SalesOrder::generateSONumber();
        $data->order_date = $request->order_date;
        $data->payment_mode = $request->payment_mode;
        $data->payment_term = $request->payment_term;
        $data->customer_id = $request->customer_id;
        $data->added_by_id = auth()->user()->id;
        $data->status_id = 1; //set to pending
        $data->save();

        $totalAmount = 0;
        $totalDiscount = 0;

        foreach($request->items as $item){
            $price = $item['unit_cost']; // map unit_cost to price
            $discount = $item['discount_per_unit'] ?? 0;
            $quantity = $item['quantity'];

            $data->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'price' => $price,
                'batch_code' => $item['batch_code'],
                'discount_per_unit' => $discount,
            ]);

            $totalAmount += ($price - $discount) * $quantity;
            $totalDiscount += $discount * $quantity;

            // Deduct inventory
            $this->inventoryService->deductStock($item['product_id'], $item['quantity'], 'Sale - SO#' . $data->so_number);
        }

        // Update totals
        $data->update([
            'total_amount' => $totalAmount,
            'total_discount' => $totalDiscount,
        ]);

        // Reload the data with relationships
        $data = SalesOrder::with('items')->find($data->id);

        return [
            'data' => new SalesOrderResource($data),
            'message' => 'Sales Order saved successfully!',
            'info' => "You've successfully saved the Sales Order"
        ];
    }


    public function update($request){
        $data = SalesOrder::findOrFail($request->id);
        $data->update([
            'customer_id' => $request->customer_id,
            'payment_mode' => $request->payment_mode,
            'payment_term' => $request->payment_term,
            'order_date' => $request->order_date,
        ]);
        // Clear existing items
        $data->items()->delete();
        // Add new items
        $totalAmount = 0;
        $totalDiscount = 0;
        foreach($request->items as $item){
            $price = $item['unit_cost']; // map unit_cost to price
            $discount = $item['discount_per_unit'] ?? 0;
            $quantity = $item['quantity'];

            $data->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'price' => $price,
                'batch_code' => $item['batch_code'],
                'discount_per_unit' => $discount,
            ]);

            $totalAmount += ($price - $discount) * $quantity;
            $totalDiscount += $discount * $quantity;
        }

        // Update totals
        $data->update([
            'total_amount' => $totalAmount,
            'total_discount' => $totalDiscount,
        ]);

        return [
            'data' => new SalesOrderResource($data),
            'message' => 'Sales Order updated successfully!',
            'info' => "You've successfully updated the Sales Order"
        ];
    }

    public function cancel($id){
        $data = SalesOrder::findOrFail($id);
        $data->update([
            'status_id' => 2, //set to cancelled
        ]);

        return [
            'data' => $data,
            'message' => 'Sales Order deleted was successful!',
            'info' => "You've successfully deleted the Sales Order"
        ];
    }

    public function dashboard(){
        $totalSalesOrders = SalesOrder::count();

        $todayOrders = SalesOrder::whereDate('created_at', today())->count();

        $totalRevenue = SalesOrder::with('items')->get()->sum(function ($order) {
            return $order->items->sum(function ($item) {
                return $item->quantity * $item->unit_cost;
            });
        });

        $pendingOrders = SalesOrder::where('status_id', 1)->count(); // Assuming status_id 1 is pending

        return [
            'total_sales_orders' => $totalSalesOrders,
            'today_orders' => $todayOrders,
            'total_revenue' => $totalRevenue,
            'pending_orders' => $pendingOrders,
        ];
    }

    public function stockAvailability(){
        // Get all products with their details
        $products = Product::with(['brand', 'unit'])->get();

        $stockData = [];
        $totalKgLeft = 0;
        $fiveKgSacks = 0;
        $tenKgSacks = 0;
        $twentyFiveKgSacks = 0;

        foreach($products as $product){
            $currentStock = $this->inventoryService->getCurrentStock($product->id);

            if($currentStock > 0){
                $totalKg = $currentStock * $product->pack_size;

                $stockData[] = [
                    'product_name' => $product->name,
                    'brand_name' => $product->brand ? $product->brand->name : 'No Brand',
                    'pack_size' => $product->pack_size,
                    'unit' => $product->unit ? $product->unit->name : 'No Unit',
                    'total_quantity' => $currentStock,
                    'total_kg' => $totalKg
                ];

                $totalKgLeft += $totalKg;

                // Count sacks based on pack_size
                if($product->pack_size == 5){
                    $fiveKgSacks += $currentStock;
                } elseif($product->pack_size == 10){
                    $tenKgSacks += $currentStock;
                } elseif($product->pack_size == 25){
                    $twentyFiveKgSacks += $currentStock;
                }
            }
        }

        return [
            'total_kg_left' => $totalKgLeft,
            'five_kg_sacks_left' => $fiveKgSacks,
            'ten_kg_sacks_left' => $tenKgSacks,
            'twenty_five_kg_sacks_left' => $twentyFiveKgSacks,
            'products' => $stockData
        ];
    }
}
