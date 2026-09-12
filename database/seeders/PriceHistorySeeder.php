<?php

namespace Database\Seeders;

use App\Models\PriceHistory;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PriceHistorySeeder extends Seeder
{
    /**
     * Genera histórico de precios de prueba para las propiedades activas.
     */
    public function run()
    {
        $properties = Property::where('status', 1)
            ->where('price', '>', 0)
            ->limit(50)
            ->get();

        if ($properties->isEmpty()) {
            $this->command->info('No hay propiedades activas para sembrar histórico.');

            return;
        }

        $generated = 0;

        foreach ($properties as $property) {
            if (PriceHistory::forProperty($property->id)->exists()) {
                continue;
            }

            $price = (float) $property->price;
            $now = Carbon::now();

            // 3 hitos: hace ~8, ~4 y ~1 mes
            $milestones = [
                ['months' => 8, 'factor' => 0.90, 'status' => 'listed'],
                ['months' => 4, 'factor' => 0.95, 'status' => 'price_changed'],
                ['months' => 1, 'factor' => 1.00, 'status' => 'listed'],
            ];

            foreach ($milestones as $milestone) {
                $entryPrice = round($price * $milestone['factor'], 2);

                PriceHistory::create([
                    'property_id' => $property->id,
                    'price' => $entryPrice,
                    'price_per_sqm' => null,
                    'status' => $milestone['status'],
                    'transaction_type' => in_array((int) $property->getRawOriginal('propery_type'), [1, 3]) ? 'rental' : 'sale',
                    'days_on_market' => max(0, (int) $milestone['months'] * 30),
                    'notes' => 'Seed de histórico de prueba (PriceHistorySeeder)',
                    'recorded_by' => null,
                    'created_at' => $now->copy()->subMonths($milestone['months']),
                    'updated_at' => $now->copy()->subMonths($milestone['months']),
                ]);
                $generated++;
            }
        }

        $this->command->info("Histórico generado: {$generated} registros.");
    }
}