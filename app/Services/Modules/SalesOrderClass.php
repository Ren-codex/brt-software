<?php

namespace App\Services\Modules;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\ArInvoice;
use App\Models\Receipt;
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
        $query = SalesOrder::with([
            'items', 
            'arInvoices',
            'customer',
            'status',
            'sub_status',
            'created_by',
            'salesRep',
            'driver'
        ])
            ->when($request->keyword, function ($query,$keyword) {
                $query->where('so_number', 'LIKE', "%{$keyword}%")
                      ->orWhereHas('status', function($q) use ($keyword){
                          $q->where('name', 'LIKE', "%{$keyword}%");
                      })
                      ->orWhereHas('customer', function($q) use ($keyword){
                          $q->where('name', 'LIKE', "%{$keyword}%");
                      });
            })
            ->when($request->status, function ($query, $status) {
                $statuses = is_array($status) ? $status : [$status];
                $statuses = array_values(array_filter($statuses));

                $query->whereHas('status', function($q) use ($statuses){
                    $q->whereIn('slug', $statuses);
                });
            })
            ->when($request->sub_status, function ($query, $sub_status) {
                $query->whereHas('subStatus', function($q) use ($sub_status){
                    $q->where('slug', $sub_status);
                });
            })
            ->when($request->location_id, function ($query, $location_id) {
                $query->where('location_id', $location_id);
            })
            ->when($request->status_id, function ($query, $status_id) {
                $query->where('status_id', $status_id);
            });

        // if ($request->is_external) {
        //     $externalLocationIds = \App\Models\ListLocation::where('name', '!=', 'Zamboanga City')->pluck('id');
        //     $query->whereIn('location_id', $externalLocationIds);
        // } else {
        //     $internalLocationIds = \App\Models\ListLocation::where('name', 'Zamboanga City')->pluck('id');
        //     $query->where(function ($q) use ($internalLocationIds) {
        //         $q->whereIn('location_id', $internalLocationIds)
        //           ->orWhereNull('location_id');
        //     });
        // }

        $data = SalesOrderResource::collection(
            $query->orderBy('created_at', 'DESC')
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

        $externalLocationIds = \App\Models\ListLocation::where('name', '!=', 'Zamboanga City')->pluck('id');
        $isExternal = in_array($request->location_id, $externalLocationIds->toArray());
        $prefix = $isExternal ? 'SO-EXT' : 'SO';
        $data = null;
        $maxAttempts = 5;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $candidate = new SalesOrder();
            $candidate->so_number = SalesOrder::generateSoNumber(null, $prefix);
            $candidate->order_date = $request->order_date;
            $candidate->customer_id = $request->customer_id;
            $candidate->sales_rep_id = $request->sales_rep_id;
            $candidate->driver_id = $request->driver_id;
            $candidate->payment_mode = $request->payment_mode;
            $candidate->due_date = strtolower($request->payment_mode) === 'credit' ? $request->due_date : null;
            $candidate->location_id = $request->location_id;
            $candidate->added_by_id = auth()->user()->id;
            $candidate->status_id = ListStatus::getBySlug('for-payment')->id; // set to "For Payment"

            try {
                $candidate->save();
                $data = $candidate;
                break;
            } catch (QueryException $e) {
                // Retry only for duplicate so_number unique key collisions.
                $isDuplicate = ((string) $e->getCode() === '23000')
                    && str_contains(strtolower($e->getMessage()), 'sales_orders_so_number_unique');
                if (!$isDuplicate || $attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

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

        // // Restore old stock
        // foreach($data->items as $item){
        //     $this->inventoryService->addStock($item->product_id, $item->quantity, 'Update SO - Restore Old Stock - SO#' . $data->so_number, $item->batch_code);
        // }

        $data->update([
            'customer_id' => $request->customer_id,
            'order_date' => $request->order_date,
            'sales_rep_id' => $request->sales_rep_id,
            'driver_id' => $request->driver_id,
            'payment_mode' => $request->payment_mode,
            'due_date' => strtolower($request->payment_mode) === 'credit' ? $request->due_date : null,
            'location_id' => $request->location_id,
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

    public function approve($id, $itemIds = []){
        $data = SalesOrder::findOrFail($id);

        // Check if this is a sales return (status is 'sales-return-approval')
        $currentStatus = $data->status ? $data->status->slug : '';
        
        if ($currentStatus === 'sales-return-approval') {
            // Determine which items to process
            $itemsToProcess = $data->items;
            $itemsNotReturned = collect();
            
            if (!empty($itemIds)) {
                // Filter to only the selected items (items being returned)
                $itemsToProcess = $data->items->whereIn('id', $itemIds);
                // Items NOT selected for return should be treated as loss/damaged (keep deducted from inventory)
                $itemsNotReturned = $data->items->whereNotIn('id', $itemIds);
            }
            
            // Process each item being returned - restore inventory for returned items
            foreach ($itemsToProcess as $item) {
                $this->inventoryService->addStock(
                    $item->product_id, 
                    $item->quantity, 
                    'Sales Return Approved - SO#' . $data->so_number, 
                    $item->batch_code
                );
            }

            // Items not selected for return are recorded as loss/damaged in inventory history.
            // We add back first then deduct as "loss" so inventory audit shows the loss event.
            foreach ($itemsNotReturned as $item) {
                $this->inventoryService->addStock(
                    $item->product_id,
                    $item->quantity,
                    'Sales Return Intake (Loss/Damaged) - SO#' . $data->so_number,
                    $item->batch_code
                );

                $this->inventoryService->recordLossOrDamage(
                    $item->product_id,
                    $item->quantity,
                    'Sales Return Loss/Damaged - SO#' . $data->so_number,
                    $item->batch_code,
                    'loss'
                );
            }

            // If all items are being returned, void the entire sales order
            // Otherwise, update the sales order with partial return handling
            $allItemIds = $data->items->pluck('id')->toArray();
            $isFullReturn = empty($itemIds) || count($itemIds) === count($allItemIds);
            
            if ($isFullReturn) {
                // Update sales order status to 'sales-returned' (loss/damaged)
                $data->update([
                    'status_id' => ListStatus::getBySlug('sales-returned')->id,
                    'approved_by_id' => auth()->user()->id,
                    'approved_at' => now(),
                ]);

                // Find and void related receipts (for full return)
                $arInvoices = ArInvoice::where('sales_order_id', $id)->get();
                
                foreach ($arInvoices as $invoice) {
                    // Find receipts related to this AR invoice
                    $receipts = Receipt::where('ar_invoice_id', $invoice->id)->get();
                    
                    foreach ($receipts as $receipt) {
                        // Update receipt status to cancelled (void)
                        $receipt->update([
                            'status_id' => ListStatus::getBySlug('cancelled')->id,
                        ]);
                    }
                    
                    // Also void the AR invoice
                    $invoice->update([
                        'status_id' => ListStatus::getBySlug('cancelled')->id,
                    ]);
                }

                return [
                    'data' => SalesOrder::find($id),
                    'message' => 'Sales Order return approved successfully!',
                    'info' => "You've successfully approved the full Sales Order return. The related receipts have been voided."
                ];
            } else {
                // Partial return - keep the sales order but mark as partially returned
                $data->update([
                    'status_id' => ListStatus::getBySlug('sales-returned')->id,
                    'approved_by_id' => auth()->user()->id,
                    'approved_at' => now(),
                ]);

                // For partial returns, we adjust the AR invoice balance
                $arInvoice = $data->arInvoices()->first();
                if ($arInvoice) {
                    // Calculate the amount to deduct from the balance (only for returned items)
                    $returnAmount = $itemsToProcess->sum(function($item) {
                        return $item->quantity * $item->price;
                    });
                    
                    // Update the invoice balance
                    $newBalanceDue = max(0, $arInvoice->balance_due - $returnAmount);
                    $arInvoice->update([
                        'balance_due' => $newBalanceDue,
                    ]);
                }

                return [
                    'data' => SalesOrder::find($id),
                    'message' => 'Partial Sales Order return approved successfully!',
                    'info' => "You've successfully approved the partial Sales Order return. The inventory has been restored for returned items and the invoice has been adjusted."
                ];
            }
        } else {
            // Regular approval for non-return sales orders
            $data->update([
                'status_id' => ListStatus::getBySlug('approved')->id,
                'approved_by_id' => auth()->user()->id,
                'approved_at' => now(),
            ]);

            return [
                'data' => SalesOrder::find($id),
                'message' => 'Sales Order approved successfully!',
                'info' => "You've successfully approved the Sales Order"
            ];
        }
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

    public function adjustment($request){
        $sales_order = SalesOrder::findOrFail($request->id);
  
        // Normalize values like "Sales Return", "sales-return", "sales_return"
        $normalizedType = Str::of((string) $request->type)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->value();

        // Set sub-status based on type
        $status = null;
        if ($normalizedType === 'sales return') {
            $status = ListStatus::getBySlug('sales-return-approval');
        } elseif ($normalizedType === 'sales allowance') {
            $status = ListStatus::getBySlug('allowance-applied');
        }

        // If status is not found, return an error
        if (!$status) {
            return [
                'data' => $sales_order,
                'message' => 'Invalid adjustment type!',
                'info' => 'Please specify a valid adjustment type (Sales Return or Sales Allowance)',
                'status' => 'error',
            ];
        }

        $sales_order->update([ 'status_id' => $status->id]);

        // Persist item-level selection for sales return requests.
        // This will be used to preselect items during approval.
        if ($normalizedType === 'sales return') {
            $selectedItemIds = collect($request->item_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            $orderItemIds = $sales_order->items()->pluck('id');

            DB::table('sales_return_items')
                ->whereIn('sales_order_item_id', $orderItemIds)
                ->delete();

            if ($selectedItemIds->isNotEmpty()) {
                $rows = $selectedItemIds
                    ->intersect($orderItemIds)
                    ->map(fn ($itemId) => [
                        'sales_order_item_id' => $itemId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->values()
                    ->all();

                if (!empty($rows)) {
                    DB::table('sales_return_items')->insert($rows);
                }
            }
        }


        return [
            'data' => $sales_order,
            'message' => 'Sales adjustment applied successfully!',
            'info' => "You've successfully applied the sales adjustment"
        ];
    }
}
