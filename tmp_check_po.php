<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'pending_status=' . (\App\Models\ListStatus::where('name', 'Pending')->value('id') ?? 'null') . PHP_EOL;
echo 'purchase_request_series=' . (\App\Models\Series::where('slug', 'purchase_request')->value('id') ?? 'null') . PHP_EOL;
echo 'suppliers_count=' . \Illuminate\Support\Facades\DB::table('list_suppliers')->count() . PHP_EOL;
echo 'products_count=' . \Illuminate\Support\Facades\DB::table('products')->count() . PHP_EOL;
echo 'purchase_orders_table=' . (\Illuminate\Support\Facades\Schema::hasTable('purchase_orders') ? 'yes' : 'no') . PHP_EOL;
echo 'purchase_order_items_table=' . (\Illuminate\Support\Facades\Schema::hasTable('purchase_order_items') ? 'yes' : 'no') . PHP_EOL;
