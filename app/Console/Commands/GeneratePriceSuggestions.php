<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\PriceIntelligenceService;
use Illuminate\Console\Command;
use Throwable;

class GeneratePriceSuggestions extends Command
{
    protected $signature = 'price:generate-suggestions {--limit=100} {--force}';

    protected $description = 'Genera sugerencias de precio con IA para las propiedades activas';

    public function handle(PriceIntelligenceService $service)
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');

        $properties = Property::with('category')
            ->where('status', 1)
            ->where('request_status', 'approved')
            ->where('price', '>', 0)
            ->limit($limit)
            ->get();

        $this->info("Generando sugerencias para {$properties->count()} propiedades...");

        $bar = $this->output->createProgressBar($properties->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($properties as $property) {
            try {
                $service->generatePriceSuggestion($property, $force);
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

        return self::SUCCESS;
    }
}