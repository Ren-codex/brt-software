<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ContactsTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('contacts')->delete();
        
        \DB::table('contacts')->insert(array (
            0 => array (
                'id' => 1,
                'name' => 'Marco Reyes',
                'email' => 'marco.reyes@ricetraders.ph',
                'phone' => '+63 912 345 6789',
                'message' => 'Hello, I am interested in purchasing premium Filipino rice. Can you provide a price list for bulk orders? We are a rice distributor based in Manila.',
                'is_read' => 1,
                'created_at' => '2026-01-15 10:30:00',
                'updated_at' => '2026-01-15 10:30:00',
            ),
            1 => array (
                'id' => 2,
                'name' => 'Chen Wei',
                'email' => 'chen.wei@orientalgrains.cn',
                'phone' => '+86 138 0000 1234',
                'message' => 'We are looking for a reliable supplier of long grain rice. Our company imports rice to China and we need at least 500 metric tons monthly.',
                'is_read' => 1,
                'created_at' => '2026-01-20 14:45:00',
                'updated_at' => '2026-01-20 14:45:00',
            ),
            2 => array (
                'id' => 3,
                'name' => 'Ahmed Hassan',
                'email' => 'ahmed.h@alrahmatrading.ae',
                'phone' => '+971 50 123 4567',
                'message' => 'Greetings! We are interested in importing premium basmati rice from your company. Please send us your product catalog and minimum order requirements.',
                'is_read' => 0,
                'created_at' => '2026-02-01 09:15:00',
                'updated_at' => '2026-02-01 09:15:00',
            ),
            3 => array (
                'id' => 4,
                'name' => 'Maria Santos',
                'email' => 'maria.santos@comidaph.com',
                'phone' => '+63 922 987 6543',
                'message' => 'Hi! I found your website through a search and I am impressed with your rice products. Could you provide more information about your organic rice options and certifications?',
                'is_read' => 0,
                'created_at' => '2026-02-05 16:20:00',
                'updated_at' => '2026-02-05 16:20:00',
            ),
            4 => array (
                'id' => 5,
                'name' => 'Kenji Tanaka',
                'email' => 'k.tanaka@osaka-foods.jp',
                'phone' => '+81 90 1234 5678',
                'message' => 'We are looking for short grain rice for our restaurant chain in Japan. Could you provide samples and pricing for monthly deliveries of 50 tons?',
                'is_read' => 1,
                'created_at' => '2026-02-10 11:00:00',
                'updated_at' => '2026-02-10 11:00:00',
            ),
            5 => array (
                'id' => 6,
                'name' => 'Priya Sharma',
                'email' => 'priya.s@spiceimpex.in',
                'phone' => '+91 98765 43210',
                'message' => 'Hello! We are an Indian rice exporter looking to expand our market. We specialize in basmati and non-basmati rice. Would you be interested in a business partnership?',
                'is_read' => 0,
                'created_at' => '2026-02-12 13:30:00',
                'updated_at' => '2026-02-12 13:30:00',
            ),
            6 => array (
                'id' => 7,
                'name' => 'David Okonkwo',
                'email' => 'd.okonkwo@lagosrice.ng',
                'phone' => '+234 803 123 4567',
                'message' => 'Good day! We are a Nigerian rice distributor looking for suppliers. We need large quantities of parboiled rice. Please send us your best price offer.',
                'is_read' => 0,
                'created_at' => '2026-02-15 08:45:00',
                'updated_at' => '2026-02-15 08:45:00',
            ),
            7 => array (
                'id' => 8,
                'name' => 'Sophie Martin',
                'email' => 'sophie.m@paris-gastronomie.fr',
                'message' => 'Hello! I run a French restaurant and I am looking for premium quality rice for risotto. Do you have arborio rice available for export to France?',
                'phone' => '+33 6 12 34 56 78',
                'is_read' => 1,
                'created_at' => '2026-02-18 15:10:00',
                'updated_at' => '2026-02-18 15:10:00',
            ),
            8 => array (
                'id' => 9,
                'name' => 'Ricardo Gomez',
                'email' => 'rgomez@santiago-dist.cl',
                'phone' => '+56 9 8765 4321',
                'message' => 'We are interested in importing medium grain rice to Chile. What are your payment terms and shipping options? We require FOB pricing.',
                'is_read' => 0,
                'created_at' => '2026-02-20 10:00:00',
                'updated_at' => '2026-02-20 10:00:00',
            ),
            9 => array (
                'id' => 10,
                'name' => 'Anna Kowalski',
                'email' => 'anna.k@warsaw-foods.pl',
                'phone' => '+48 123 456 789',
                'message' => 'Good afternoon! We are looking for a rice supplier for our retail chain in Poland. Please provide information about your packaging options and delivery times to Europe.',
                'is_read' => 0,
                'created_at' => '2026-02-22 14:25:00',
                'updated_at' => '2026-02-22 14:25:00',
            ),
        ));
    }
}
