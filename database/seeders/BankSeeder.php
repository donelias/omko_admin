<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Cooperative;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['name' => 'La Asociación Popular de Ahorros y Préstamos (APAP)', 'interest_rate' => 10.50, 'currency' => 'DOP', 'sort_order' => 1],
            ['name' => 'Banco de Reservas (Banreservas)', 'interest_rate' => 12.47, 'currency' => 'DOP', 'sort_order' => 2],
            ['name' => 'Banco Popular Dominicano', 'interest_rate' => 13.95, 'currency' => 'DOP', 'sort_order' => 3],
            ['name' => 'Banco BHD', 'interest_rate' => 13.95, 'currency' => 'DOP', 'sort_order' => 4],
            ['name' => 'Scotiabank', 'interest_rate' => 13.50, 'currency' => 'DOP', 'sort_order' => 5],
            ['name' => 'Banco Santa Cruz', 'interest_rate' => 13.00, 'currency' => 'DOP', 'sort_order' => 6],
            ['name' => 'Asociación Cibao de Ahorros y Préstamos', 'interest_rate' => 14.50, 'currency' => 'DOP', 'sort_order' => 7],
            ['name' => 'Banco Caribe', 'interest_rate' => 12.50, 'currency' => 'DOP', 'sort_order' => 8],
            ['name' => 'Banco Vimenca', 'interest_rate' => 13.60, 'currency' => 'DOP', 'sort_order' => 9],
            ['name' => 'Banco Promerica', 'interest_rate' => 10.25, 'currency' => 'USD', 'sort_order' => 10],
        ];

        foreach ($banks as $bank) {
            Bank::create($bank);
        }

        $cooperatives = [
            ['name' => 'COOPNAMA', 'interest_rate' => 12.00, 'currency' => 'DOP', 'sort_order' => 1],
            ['name' => 'COOPMAIMÓN', 'interest_rate' => 13.50, 'currency' => 'DOP', 'sort_order' => 2],
            ['name' => 'Cooperativa San José', 'interest_rate' => 12.50, 'currency' => 'DOP', 'sort_order' => 3],
            ['name' => 'Cooperativa Vega Real', 'interest_rate' => 13.00, 'currency' => 'DOP', 'sort_order' => 4],
            ['name' => 'Coopsano', 'interest_rate' => 12.75, 'currency' => 'DOP', 'sort_order' => 5],
            ['name' => 'Coopcentral', 'interest_rate' => 13.25, 'currency' => 'DOP', 'sort_order' => 6],
            ['name' => 'Coopmédica', 'interest_rate' => 12.25, 'currency' => 'DOP', 'sort_order' => 7],
            ['name' => 'COOPPAIS', 'interest_rate' => 13.00, 'currency' => 'DOP', 'sort_order' => 8],
            ['name' => 'Cooperativa La Nacional', 'interest_rate' => 12.50, 'currency' => 'DOP', 'sort_order' => 9],
            ['name' => 'Cooperativa Maimón', 'interest_rate' => 13.75, 'currency' => 'DOP', 'sort_order' => 10],
        ];

        foreach ($cooperatives as $coop) {
            Cooperative::create($coop);
        }
    }
}
