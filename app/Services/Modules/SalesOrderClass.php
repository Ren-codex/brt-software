<?php

namespace App\Services\Modules;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Models\SalesOrder;
use App\Models\ArInvoice;
use App\Models\InventoryStocks;
use App\Models\ReceivedItem;
use App\Models\Product;
use App\Models\ListStatus;
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
            SalesOrder::with(['items', 'arInvoices'])
                ->when($request->keyword, function ($query,$keyword) {
                    $query->where('so_number', 'LIKE', "%{$keyword}%")
                          ->orWhereHas('status', function($q) use ($keyword){
                              $q->where('name', 'LIKE', "%{$keyword}%");
                          })
                          ->orWhereHas('customer', function($q) use ($keyword){
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
            if (!$this->inventoryService->hasSufficientStock($item['product_id'], $item['quantity'], $item['batch_code'])) {
                $product = Product::find($item['product_id']);
                throw ValidationException::withMessages(['stock' => 'Insufficient stock for product: ' . ($product ? $product->name : 'Unknown Product') . ' in batch ' . $item['batch_code']]);
            }
        }

        //dd( $request->all());
        $data = new SalesOrder();
        $data->so_number = SalesOrder::generateSONumber();
        //dd(SalesOrder::generateSONumber());
        $data->order_date = $request->order_date;
        $data->customer_id = $request->customer_id;
        $data->sales_rep_id = $request->sales_rep_id;
        $data->driver_id = $request->driver_id;
        $data->payment_mode = $request->payment_mode;
        $data->due_date = $request->due_date;
        $data->added_by_id = auth()->user()->id;
        $data->status_id = ListStatus::getBySlug('for-payment')->id; //set to "For Payment" 

        $data->save();

        //dd($data->id);


        $totalAmount = 0;
        $totalDiscount = 0;

        foreach($request->items as $item){
            $price = $item['price']; // map price to price
            $discount_per_unit = $item['discount_per_unit'] ?? 0;
            $quantity = $item['quantity'];
            $total_discount_amount = $discount_per_unit * $quantity;

       
            $data->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'price' => $price,
                'price_type' => $item['price_type'],
                'batch_code' => $item['batch_code'],
                'discount_per_unit' => $discount_per_unit,
            ]);

            $totalAmount += ($price * $quantity) - $total_discount_amount;
            $totalDiscount += $total_discount_amount;


            // Deduct inventory
            $this->inventoryService->deductStock($item['product_id'], $item['quantity'], 'Sale - SO#' . $data->so_number, $item['batch_code']);
        }

        // Update totals
        $data->update([
            'total_amount' => $totalAmount,
            'total_discount' => $totalDiscount,
        ]);

        // Reload the data with relationships
        $data = SalesOrder::with(['items', 'customer', 'status', 'created_by'])->find($data->id);
 
     
        // Create AR Invoice
        $invoice = new ArInvoice();
        $invoice->sales_order_id = $data->id;
        $invoice->invoice_number = ArInvoice::generateInvoiceNumber();
        $invoice->invoice_date = $data->order_date;
        $invoice->amount_paid = 0;
        $invoice->balance_due = $data->total_amount;
        $invoice->total_discount = $data->total_discount;
        $invoice->status_id = ListStatus::getBySlug('unpaid')->id; // Unpaid
        $invoice->save();
        
    
        return [
            'data' => new SalesOrderResource($data),
            'message' => 'Sales Order saved successfully!',
            'info' => "You've successfully saved the Sales Order"
        ];
    }


    public function update($request){

        $data = SalesOrder::findOrFail($request->id);

        // Restore old stock
        foreach($data->items as $item){
            $this->inventoryService->addStock($item->product_id, $item->quantity, 'Update SO - Restore Old Stock - SO#' . $data->so_number, $item->batch_code);
        }

        $data->update([
            'customer_id' => $request->customer_id,
            'order_date' => $request->order_date,
            'sales_rep_id' => $request->sales_rep_id,
            'driver_id' => $request->driver_id,
            'payment_mode' => $request->payment_mode,
            'due_date' => $request->due_date,
            'updated_by_id' => auth()->user()->id,
        ]);

        // Clear existing items
        $data->items()->delete();

        // Validate stock availability for new items
        foreach($request->items as $item){
            if (!$this->inventoryService->hasSufficientStock($item['product_id'], $item['quantity'], $item['batch_code'])) {
                $product = Product::find($item['product_id']);
                throw ValidationException::withMessages(['stock' => 'Insufficient stock for product: ' . ($product ? $product->name : 'Unknown Product') . ' in batch ' . $item['batch_code']]);
            }
        }

        // Add new items
        $totalAmount = 0;
        $totalDiscount = 0;
        foreach($request->items as $item){
            $price = $item['price']; // map price to price
            $discount_per_unit = $item['discount_per_unit'] ?? 0;
            $quantity = $item['quantity'];
            $total_discount_amount = $discount_per_unit * $quantity;

            $data->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'price' => $price,
                'price_type' => $item['price_type'],
                'batch_code' => $item['batch_code'],
                'discount_per_unit' => $discount_per_unit,
            ]);

            $totalAmount += ($price * $quantity) - $total_discount_amount;
            $totalDiscount += $total_discount_amount;

            // Deduct new inventory
            $this->inventoryService->deductStock($item['product_id'], $item['quantity'], 'Update Sale - SO#' . $data->so_number, $item['batch_code']);
        }

        // Update totals
        $data->update([
            'total_amount' => $totalAmount,
            'total_discount' => $totalDiscount,
        ]);

        // Update associated invoice
        $invoice = $data->arInvoices()->first();
        if ($invoice) {
            $invoice->update([
                'balance_due' => $totalAmount,
                'total_discount' => $totalDiscount,
            ]);
        }

        // Reload the data with relationships
        $data = SalesOrder::with(['items', 'customer', 'status', 'updated_by', 'arInvoices'])->find($data->id);

        return [
            'data' => new SalesOrderResource($data),
            'message' => 'Sales Order updated successfully!',
            'info' => "You've successfully updated the Sales Order"
        ];
    }

    public function approve($id){
        $data = SalesOrder::findOrFail($id);

        $data->update([
            'status_id' => ListStatus::getBySlug('approved')->id, //set to approved
            'approved_by_id' => auth()->user()->id,
            'approved_at' => now(),
        ]);

        return [
            'data' => SalesOrder::find($id),
            'message' => 'Sales Order approved successfully!',
            'info' => "You've successfully approved the Sales Order"
        ];
    }

    public function cancel($id){
        $data = SalesOrder::findOrFail($id);

        // Restore stock
        foreach($data->items as $item){
            $this->inventoryService->addStock($item->product_id, $item->quantity, 'Cancel SO - Restore Stock - SO#' . $data->so_number, $item->batch_code);
        }

        $data->update([
            'status_id' => ListStatus::getBySlug('cancelled')->id, //set to cancelled
        ]);

        return [
            'data' => SalesOrder::find($id),
            'message' => 'Sales Order cancelled successfully!',
            'info' => "You've successfully cancelled the Sales Order"
        ];
    }

    public function dashboard(){
        $total_sales_orders = SalesOrder::count();
        $today_orders = SalesOrder::whereDate('created_at', today())->count();
        $total_revenue = SalesOrder::where('status_id', '!=', ListStatus::getBySlug('cancelled')->id)->sum('total_amount') ?? 0; // Exclude cancelled orders (status_id 2)
        $pending_orders = SalesOrder::where('status_id', ListStatus::getBySlug('pending')->id)->count(); // Assuming status_id 1 is pending
        $cancelled_orders = SalesOrder::where('status_id', ListStatus::getBySlug('cancelled')->id)->count(); // status_id 2 is cancelled

        return [
            'total_sales_orders' => $total_sales_orders,
            'today_orders' => $today_orders,
            'total_revenue' => $total_revenue,
            'pending_orders' => $pending_orders,
            'cancelled_orders' => $cancelled_orders,
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

     public function adjustment(){
        // Get all sales orders with status 'adjusted' (assuming status_id 3 is 'adjusted')
        $adjusted_orders = SalesOrder::where('status_id', ListStatus::getBySlug('adjusted')->id)->with('items')->get();

        return $adjusted_orders; 
    }
}
