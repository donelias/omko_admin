<?php

namespace App\Jobs;

use App\Services\PriceMarketAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Refresca el análisis de mercado de una zona con Google Search Grounding.
 *
 * Es único por zona: si ya hay un refresco en cola para la misma zona,
 * Laravel no encola otro (evita gastar cuota de Gemini por duplicado).
 */
class RefreshMarketZoneJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Ventana de unicidad en segundos.
     */
    public int $uniqueFor = 3600;

    /**
     * Timeout del job (la búsqueda con grounding puede tardar).
     */
    public int $timeout = 180;

    /**
     * @param  string|null  $zoneKey
     */
    public function __construct(public ?string $zoneKey) {}

    public function uniqueId(): string
    {
        return (string) $this->zoneKey;
    }

    public function handle(PriceMarketAnalysisService $service): void
    {
        try {
            $result = $service->refreshZone($this->zoneKey, true);

            Log::info('RefreshMarketZoneJob finalizado', [
                'zone_key' => $this->zoneKey,
                'status' => $result['status'] ?? null,
                'comparables' => $result['analysis']->comparable_count ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('RefreshMarketZoneJob error: '.$e->getMessage(), [
                'zone_key' => $this->zoneKey,
            ]);

            throw $e;
        }
    }
}
