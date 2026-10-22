<?php

namespace App\Console\Commands;

use App\Models\PriceSuggestion;
use App\Models\Property;
use App\Services\PriceIntelligenceService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class GeneratePriceSuggestions extends Command
{
    protected $signature = 'price:generate-suggestions {--limit=100} {--force} {--all}';

    protected $description = 'Genera sugerencias de precio con IA para las propiedades activas';

    public function handle(PriceIntelligenceService $service)
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $all = (bool) $this->option('all');

        $query = Property::with('category')
            ->where('status', 1)
            ->where('request_status', 'approved')
            ->where('price', '>', 0);

        if (! $force && ! $all) {
            // Sólo las que no tienen sugerencia o la tienen vencida.
            $query->where(function (Builder $query) {
                $query->whereDoesntHave('priceSuggestion')
                    ->orWhereHas('priceSuggestion', function (Builder $query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '<=', now());
                    });
            });
        }

        // Orden determinista: primero las más antiguas por sugerencia, luego por id
        $properties = $query
            ->orderByRaw('(SELECT price_suggestions.expires_at FROM price_suggestions WHERE price_suggestions.property_id = propertys.id) IS NOT NULL')
            ->orderBy('id')
            ->limit($all ? 0 : $limit)
            ->get();

        if ($properties->isEmpty()) {
            $this->info('No hay propiedades que necesitan sugerencia de precio.');

            return self::SUCCESS;
        }

        $this->info("Generando sugerencias para {$properties->count()} propiedades...");

        $bar = $this->output->createProgressBar($properties->count());
        $bar->start();

        $success = 0;
        $failed = 0;
        $withMarketData = 0;

        foreach ($properties as $property) {
            try {
                $suggestion = $service->generatePriceSuggestion($property, $force);

                if ($suggestion && (int) ($suggestion->market_comparable_count ?? 0) > 0) {
                    $withMarketData++;
                }

                $success++;
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("Propiedad #{$property->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Completado: {$success} sugerencias OK, {$failed} fallidas.");

        if ($withMarketData > 0) {
            $this->info("  {$withMarketData} con análisis real de mercado (datos web de la zona).");
        }

        // Aviso de zonas pendientes de analizar (sin análisis vigente)
        $pending = PriceSuggestion::query()
            ->where('market_comparable_count', 0)
            ->whereNotNull('property_id')
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        if ($pending > 0) {
            $this->line("  {$pending} sugerencias aún sin datos web de zona (se encoló su refresco).");
        }

        return self::SUCCESS;
    }
}
