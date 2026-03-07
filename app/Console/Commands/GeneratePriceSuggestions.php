<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\PriceSuggestion;
use App\Services\PriceIntelligenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GeneratePriceSuggestions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price:generate-suggestions {--limit=100} {--force}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Generate AI price suggestions for properties';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $this->info("Generating price suggestions (limit: $limit, force: " . ($force ? 'yes' : 'no') . ")...");

        try {
            // Obtener propiedades sin sugerencias válidas
            $query = Property::where('status', 'listed')
                ->whereNotNull('price')
                ->withoutTrashed();

            if (!$force) {
                $query->whereDoesntHave('suggestion', function ($q) {
                    $q->where('expires_at', '>', now());
                });
            }

            $properties = $query->limit($limit)->get();

            $this->info("Processing {$properties->count()} properties...");

            $generated = 0;
            $failed = 0;

            foreach ($properties as $property) {
                try {
                    $suggestion = PriceIntelligenceService::generatePriceSuggestion($property, $force);

                    if ($suggestion) {
                        $this->line("✓ Property {$property->id}: {$suggestion->suggested_price}");
                        $generated++;
                    } else {
                        $this->line("✗ Property {$property->id}: No suggestion generated");
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $this->error("✗ Property {$property->id}: {$e->getMessage()}");
                    $failed++;
                }
            }

            $this->info("Completed! Generated: $generated, Failed: $failed");
            Log::info("Price suggestions generated", ['count' => $generated, 'failed' => $failed]);

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error("Error generating price suggestions: {$e->getMessage()}");
        }
    }
}
