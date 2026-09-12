<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;
use Throwable;

class FetchExchangeRates extends Command
{
    protected $signature = 'price:fetch-exchange-rates
        {--source=} : Fuerza la fuente (google|bcrd|allratestoday|setting|static) para este run
        {--set-usd=} : Guarda manualmente la tasa USD a DOP (tasa del día) y termina';

    protected $description = 'Descarga y persiste la tasa de cambio USD/DOP vigente (BCRD)';

    public function handle(ExchangeRateService $service)
    {
        $manualUsd = $this->option('set-usd');
        if ($manualUsd !== null) {
            $rate = (float) $manualUsd;
            if ($rate <= 0) {
                $this->error('--set-usd debe ser un número mayor a 0.');

                return self::FAILURE;
            }

            $meta = $service->setManual($rate);

            return $this->printMeta('Tasa guardada manualmente.', $meta);
        }

        $source = $this->option('source');
        if ($source !== null) {
            config(['global.PRICE_EXCHANGE_RATES_SOURCE' => $source]);
        }

        try {
            $meta = $service->refresh();

            return $this->printMeta('Tasas actualizadas.', $meta);
        } catch (Throwable $e) {
            $this->error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function printMeta(string $message, array $meta): int
    {
        $this->info($message);
        $this->table(
            ['Propiedad', 'Valor'],
            [
                ['base_currency', $meta['base_currency'] ?? 'DOP'],
                ['as_of (día vigente)', $meta['as_of'] ?? '—'],
                ['USD 1 → DOP', isset($meta['rates']['USD']) ? round($meta['rates']['USD'], 4) : '—'],
                ['source', $meta['source'] ?? '—'],
                ['fetched_at', $meta['fetched_at'] ?? '—'],
            ]
        );

        return self::SUCCESS;
    }
}