<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ListRolesTableSeeder::class);
        $this->call(ListStatusesTableSeeder::class);
        $this->call(ListSalariesTableSeeder::class);
        $this->call(ListPositionsTableSeeder::class);
        $this->call(UsersTableSeeder::class);
        $this->call(UserRolesTableSeeder::class);
        $this->call(EmployeesTableSeeder::class);
        $this->call(CustomersTableSeeder::class);
        $this->call(ListBrandsTableSeeder::class);
        $this->call(ListUnitsTableSeeder::class);
        $this->call(ProductsTableSeeder::class);
        $this->call(ListSuppliersTableSeeder::class);
        $this->call(PurchaseOrdersTableSeeder::class);
        $this->call(PurchaseOrderItemsTableSeeder::class);
        $this->call(ReceivedStocksTableSeeder::class);
        $this->call(ReceivedItemsTableSeeder::class);
        $this->call(InventoryStocksTableSeeder::class);
        $this->call(SeriesSeeder::class);
        $this->call(PayrollSettingsTableSeeder::class);

    }
}
