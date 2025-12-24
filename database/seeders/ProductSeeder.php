<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        $products = [
            [
                'sku' => 'IPHONE-15-PRO',
                'name' => 'iPhone 15 Pro 256GB',
                'description' => 'Smartphone Apple com chip A17 Pro',
                'cost_price' => 6000.00,
                'sale_price' => 8500.00,
            ],
            [
                'sku' => 'DELL-XPS-13',
                'name' => 'Notebook Dell XPS 13',
                'description' => 'Ultrabook fino e leve com i7',
                'cost_price' => 7000.00,
                'sale_price' => 11000.00,
            ],
            [
                'sku' => 'LOGI-MX-3',
                'name' => 'Mouse Logitech MX Master 3',
                'description' => 'Mouse ergonômico para produtividade',
                'cost_price' => 350.00,
                'sale_price' => 600.00,
            ],
            [
                'sku' => 'SAMSUNG-49',
                'name' => 'Monitor Odyssey G9',
                'description' => 'Monitor Curvo 49 polegadas 240hz',
                'cost_price' => 8000.00,
                'sale_price' => 12000.00,
            ],
            [
                'sku' => 'KEY-MECH-RGB',
                'name' => 'Teclado Mecânico Keychron',
                'description' => 'Teclado sem fio switch brown',
                'cost_price' => 600.00,
                'sale_price' => 1100.00,
            ],
        ];

        foreach ($products as $data) {
            $product = Product::create($data);

            Inventory::create([
                'product_id' => $product->id,
                'quantity' => 0,
                'last_updated' => now(),
            ]);
        }

    }
}
