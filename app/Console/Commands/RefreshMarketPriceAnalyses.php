<?php

namespace App\Console\Commands;

use App\Services\PriceMarketAnalysisService;
use Illuminate\Console\Command;

class RefreshMarketPriceAnalyses extends Command
{
    protected $signature = 'price:refresh-market
                            {--zone= : Refresca sólo una zona (clave de zona)}
                            {--property= : Refresca sólo la zona de una propiedad}
                            {--force : Refresca aunque el snapshot siga vigente}
                            {--limit=0 : Máximo de zonas a procesar (0 = todas)}';

    protected $description = 'Analiza el mercado real de cada zona con Google Search Grounding (precios web de propiedades cercanas)';

    public function handle(PriceMarketAnalysisService $service)
    {
        if (! $service->isEnabled()) {
            $this->error('PRICE_MARKET_ENABLED está desactivado en la configuración.');

            return self::FAILURE;
        }

        $zoneKey = $this->option('zone');
        $propertyId = $this->option('property');

        if ($propertyId) {
            $property = \App\Models\Property::with('category')->find($propertyId);

            if (! $property) {
                $this->error("No existe la propiedad #{$propertyId}.");

                return self::FAILURE;
            }

            $zoneKey = $service->zoneKeyForProperty($property);

            if (! $zoneKey) {
                $this->error("La propiedad #{$propertyId} no tiene coordenadas válidas.");

                return self::FAILURE;
            }
        }

        $this->info('Analizando zonas con Google Search Grounding...');
        $this->line('  Radio de zona: '.$service->radiusKm().' km');
        $this->line('  Mínimo de comparables: '.$service->minComparables());
        $this->line('  Zonas a refrescar: '.($zoneKey ?: 'todas'));

        $summary = $service->refreshAllZones(
            (bool) $this->option('force'),
            $zoneKey ?: null,
            (int) $this->option('limit')
        );

        $this->newLine(2);

        foreach ($summary['details'] as $detail) {
            $this->line(sprintf(
                '  [%s] %s — %d comparables%s',
                str_pad(strtoupper($detail['status']), 9),
                $detail['label'] ?: $detail['zone_key'],
                $detail['comparables'],
                $detail['error'] ? ' ('.$detail['error'].')' : ''
            ));
        }

        $this->newLine(2);
        $this->info(sprintf(
            'Total %d zonas: %d completas, %d parciales, %d fallidas, %d omitidas.',
            $summary['total'],
            $summary['completed'],
            $summary['partial'],
            $summary['failed'],
            $summary['skipped']
        ));

        return self::SUCCESS;
    }
}
