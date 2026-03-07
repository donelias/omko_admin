<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\PriceHistory;
use Illuminate\Database\Seeder;

class PriceHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener propiedades
        $properties = Property::limit(50)->get();

        if ($properties->isEmpty()) {
            $this->command->info('No properties found. Please seed properties first.');
            return;
        }

        foreach ($properties as $property) {
            // Crear histórico de 6-12 meses de precios
            $monthsBack = rand(6, 12);

            for ($i = $monthsBack; $i > 0; $i--) {
                $date = now()->subMonths($i);

                // Generar variación de precio (±15%)
                $variation = rand(-15, 15);
                $price = $property->price * (1 + ($variation / 100));

                PriceHistory::create([
                    'property_id' => $property->id,
                    'price' => round($price, 2),
                    'price_per_sqm' => $property->area > 0
                        ? round($price / $property->area, 2)
                        : null,
                    'status' => rand(0, 1) ? 'listed' : 'price_changed',
                    'transaction_type' => rand(0, 1) ? 'sale' : 'rental',
                    'days_on_market' => rand(5, 120),
                    'notes' => 'Auto-generated price history',
                    'recorded_by' => 1,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }

            $this->command->info("Created price history for property {$property->id}");
        }

        $this->command->info('Price history seeding completed!');
    }
}
