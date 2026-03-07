<?php

namespace Database\Seeders;

use App\Models\CommissionSetting;
use Illuminate\Database\Seeder;

class CommissionSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default commission setting
        CommissionSetting::firstOrCreate(
            ['is_active' => true],
            [
                'commission_type' => 'percentage',
                'commission_value' => 5.00,
                'currency' => 'RD$',
                'description' => 'Comisión estándar: 5% sobre el precio de la propiedad',
                'apply_to_rentals' => true,
                'apply_to_sales' => true,
            ]
        );

        $this->command->info('Commission settings seeded successfully!');
    }
}
