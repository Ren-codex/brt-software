<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
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
        $this->call(UsersTableSeeder::class);
        $this->call(UserRolesTableSeeder::class);
        $this->call(UserProfilesTableSeeder::class);
        $this->call(CustomersTableSeeder::class);
        $this->call(ListBrandsTableSeeder::class);
        $this->call(ListUnitsTableSeeder::class);
        $this->call(ListSuppliersTableSeeder::class);
        // $this->call(PurchaseOrdersTableSeeder::class);
        // $this->call(PurchaseOrderItemsTableSeeder::class);
        // $this->call(ReceivedStocksTableSeeder::class);
        // $this->call(ReceivedItemsTableSeeder::class);

    
  



    



  

    }
}
