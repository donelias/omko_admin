<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSequence;
use Illuminate\Support\Facades\DB;

/**
 * Emisión de facturas con reserva atómica del número fiscal.
 *
 * El NCF se reserva con lockForUpdate dentro de la transacción que inserta la
 * factura: dos emisiones simultáneas nunca reciben el mismo número y un
 * rollback devuelve el contador a su sitio.
 */
class InvoiceService
{
    /**
     * @param  array  $data  atributos de la factura (concepto, cliente, tasas...)
     * @param  int|null  $sequence  si se indica, reutiliza un NCF existente
     *                               (caso de actualizar una factura)
     */
    public function emit(array $data, ?int $sequence = null): Invoice
    {
        return DB::transaction(function () use ($data, $sequence) {
            $ncfType = $data['ncf_type'] ?? 'B01';

            if ($sequence === null) {
                $sequence = $this->reserveSequence($ncfType);
            }

            $data['ncf_type'] = $ncfType;
            $data['sequence'] = $sequence;
            $data['invoice_number'] = Invoice::formatNcf($ncfType, $sequence);

            return Invoice::create($data);
        });
    }

    /**
     * Reserva el siguiente número del contador para un tipo de comprobante.
     * Debe llamarse SIEMPRE dentro de la transacción de creación.
     */
    private function reserveSequence(string $ncfType): int
    {
        $row = InvoiceSequence::firstOrCreate(['ncf_type' => $ncfType], ['next_sequence' => 1]);

        $sequence = InvoiceSequence::whereKey($row->id)->lockForUpdate()->value('next_sequence');
        $sequence = (int) $sequence;

        InvoiceSequence::whereKey($row->id)->increment('next_sequence');

        return $sequence;
    }

    /**
     * Recalcula el subtotal desde concepto cantidades. Las tasas se respetan
     * tal como llegan; los totales los fija el modelo en su evento saving.
     */
    public static function rebuildSubtotal(array $data): array
    {
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $unit = (float) ($data['unit_price'] ?? 0);

        $data['quantity'] = $quantity;
        $data['subtotal'] = round($quantity * $unit, 2);

        return $data;
    }
}
