<?php

namespace App\Services;

use App\Jobs\RefreshMarketZoneJob;
use App\Models\PriceMarketAnalysis;
use App\Models\Property;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Análisis real de mercado por zona.
 *
 * A diferencia del motor estadístico (PriceIntelligenceService), que sólo
 * trabaja con las propiedades de la base de datos, este servicio consulta la
 * web mediante Google Search Grounding de Gemini para obtener precios reales
 * de propiedades cercanas a una ubicación concreta.
 *
 * La zona se define por un radio en kilómetros alrededor de las coordenadas
 * de la propiedad (lat/lng). Todas las propiedades que caen en la misma
 * celda de la grilla comparten una zona y, por tanto, un mismo análisis.
 */
class PriceMarketAnalysisService
{
    public const ALGORITHM_VERSION = '1.0';

    public function __construct(protected GeminiService $gemini) {}

    /* -----------------------------------------------------------------
     |  Configuración
     | ----------------------------------------------------------------- */

    public function isEnabled(): bool
    {
        return (bool) config('global.PRICE_MARKET_ENABLED', true);
    }

    public function radiusKm(): float
    {
        return max(0.5, (float) config('global.PRICE_MARKET_ZONE_RADIUS_KM', 3.0));
    }

    public function minComparables(): int
    {
        return max(5, (int) config('global.PRICE_MARKET_MIN_COMPARABLES', 10));
    }

    public function refreshIntervalDays(): int
    {
        return max(1, (int) config('global.PRICE_MARKET_REFRESH_DAYS', 30));
    }

    /**
     * Tamaño de la celda de la grilla, en grados. 0.02° ≈ 2.2 km de lado,
     * que es el tamaño natural de una "zona" urbana.
     */
    public function cellDegrees(): float
    {
        return max(0.005, (float) config('global.PRICE_MARKET_CELL_DEGREES', 0.02));
    }

    /* -----------------------------------------------------------------
     |  Zona (grilla de lat/lng)
     | ----------------------------------------------------------------- */

    /**
     * Clave de zona: índices de la grilla geográfica + el radio de búsqueda.
     * Dos propiedades dentro de la misma celda comparten análisis.
     */
    public function zoneKeyFor(float $lat, float $lng, ?float $radiusKm = null): string
    {
        $radius = $radiusKm ?: $this->radiusKm();
        $cell = $this->cellDegrees();

        $latIndex = floor($lat / $cell);
        $lngIndex = floor($lng / $cell);

        return sprintf('z%.4f_%.4f_r%02d', $latIndex, $lngIndex, (int) round($radius * 10));
    }

    public function zoneKeyForProperty(Property $property): ?string
    {
        $coords = $this->coordinatesOf($property);

        if (! $coords) {
            return null;
        }

        return $this->zoneKeyFor($coords['lat'], $coords['lng']);
    }

    /**
     * Coordenadas válidas de la propiedad (las columnas se guardan como
     * decimal(10,7) y pueden venir nulas o corruptas).
     */
    public function coordinatesOf(Property $property): ?array
    {
        $lat = $property->latitude !== null ? (float) $property->latitude : null;
        $lng = $property->longitude !== null ? (float) $property->longitude : null;

        if ($lat === null || $lng === null) {
            return null;
        }
        if ($lat == 0.0 || $lng == 0.0) {
            return null;
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }
        if (! $this->isPlausibleForCity($property, $lat, $lng)) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Descarta coordenadas corruptas: si el resto de propiedades de la misma
     * ciudad están en un punto y esta está a cientos de kilómetros, el dato
     * del geocodificador es incorrecto y la propiedad no genera zona.
     */
    protected function isPlausibleForCity(Property $property, float $lat, float $lng): bool
    {
        $city = trim((string) ($property->city ?? ''));

        if ($city === '') {
            return true;
        }

        $center = Cache::remember(
            'price_market_city_center_'.md5(mb_strtolower($city)),
            3600,
            function () use ($city) {
                $rows = Property::query()
                    ->whereRaw('LOWER(city) = ?', [mb_strtolower($city)])
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->where('latitude', '!=', 0)
                    ->where('longitude', '!=', 0)
                    ->get(['latitude', 'longitude']);

                if ($rows->count() < 2) {
                    return null;
                }

                // Mediana en lugar de promedio: es robusta a coordenadas corruptas
                $lats = $rows->pluck('latitude')->map(fn ($value) => (float) $value)->sort()->values();
                $lngs = $rows->pluck('longitude')->map(fn ($value) => (float) $value)->sort()->values();
                $middle = intdiv($lats->count(), 2);

                $medianLat = $lats->count() % 2 === 0
                    ? ($lats[$middle - 1] + $lats[$middle]) / 2
                    : $lats[$middle];
                $medianLng = $lngs->count() % 2 === 0
                    ? ($lngs[$middle - 1] + $lngs[$middle]) / 2
                    : $lngs[$middle];

                return ['lat' => $medianLat, 'lng' => $medianLng];
            }
        );

        if (! is_array($center)) {
            return true;
        }

        return $this->distanceKm($lat, $lng, $center['lat'], $center['lng']) <= 150;
    }

    /**
     * Distancia en kilómetros entre dos coordenadas (fórmula del haversine).
     */
    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Etiqueta legible de la zona (dirección + municipio + provincia).
     */
    public function zoneLabelFor(Property $property): string
    {
        $parts = array_filter([
            trim((string) ($property->address ?: '')),
            trim((string) ($property->city ?: '')),
            trim((string) ($property->state ?: '')),
        ]);

        $label = implode(', ', array_unique($parts));

        if ($label === '') {
            $label = 'Zona #'.substr((string) $this->zoneKeyForProperty($property), 0, 12);
        }

        return $label.' (radio '.$this->radiusKm().' km)';
    }

    /**
     * Límites geográficos de una zona a partir de su clave.
     */
    public function zoneBounds(string $zoneKey): ?array
    {
        if (! preg_match('/^z(-?[\d.]+)_(-?[\d.]+)_r(\d+)$/', $zoneKey, $matches)) {
            return null;
        }

        $cell = $this->cellDegrees();
        $latIndex = (float) $matches[1];
        $lngIndex = (float) $matches[2];

        return [
            'cell_degrees' => $cell,
            'radius_km' => ((float) $matches[3]) / 10,
            'min_lat' => $latIndex * $cell,
            'max_lat' => ($latIndex + 1) * $cell,
            'min_lng' => $lngIndex * $cell,
            'max_lng' => ($lngIndex + 1) * $cell,
        ];
    }

    /* -----------------------------------------------------------------
     |  Lectura del snapshot vigente
     | ----------------------------------------------------------------- */

    public function currentForProperty(Property $property): ?PriceMarketAnalysis
    {
        $zoneKey = $this->zoneKeyForProperty($property);

        if (! $zoneKey) {
            return null;
        }

        return PriceMarketAnalysis::currentForZone($zoneKey, $this->propertyTypeSlug($property));
    }

    public function isStale(?PriceMarketAnalysis $analysis): bool
    {
        if (! $analysis) {
            return true;
        }

        return $analysis->isStale($this->refreshIntervalDays());
    }

    /**
     * Devuelve el snapshot vigente de la zona. Si falta o está vencido,
     * encola el refresco (sin bloquear la respuesta al cliente).
     */
    public function ensureForProperty(Property $property, bool $queueRefresh = true): ?PriceMarketAnalysis
    {
        $analysis = $this->currentForProperty($property);

        if (! $this->isStale($analysis)) {
            return $analysis;
        }

        if ($queueRefresh && $this->isEnabled()) {
            $this->queueRefresh($property);
        }

        return $analysis;
    }

    /**
     * Encola el refresco de la zona de una propiedad.
     */
    public function queueRefresh(Property $property): void
    {
        $zoneKey = $this->zoneKeyForProperty($property);

        if (! $zoneKey) {
            return;
        }

        try {
            RefreshMarketZoneJob::dispatch($zoneKey);
        } catch (Throwable $e) {
            Log::warning('No se pudo encolar el refresco de zona: '.$e->getMessage());
        }
    }

    /* -----------------------------------------------------------------
     |  Refresco (búsqueda web con grounding)
     | ----------------------------------------------------------------- */

    /**
     * Analiza una zona con Google Search Grounding y guarda el snapshot.
     *
     * @param  string|null  $zoneKey
     * @param  bool  $force  Refrescar aunque el snapshot siga vigente
     * @return array ['status' => completed|partial|failed|skipped, 'analysis' => ?PriceMarketAnalysis, 'error' => ?string]
     */
    public function refreshZone(?string $zoneKey, bool $force = false): array
    {
        if (! $this->isEnabled()) {
            return ['status' => 'skipped', 'analysis' => null, 'error' => 'PRICE_MARKET_ENABLED está desactivado'];
        }

        if (empty($zoneKey)) {
            return ['status' => 'skipped', 'analysis' => null, 'error' => 'Zona sin clave'];
        }

        $anchor = $this->anchorPropertyForZone($zoneKey);

        if (! $anchor) {
            return ['status' => 'skipped', 'analysis' => null, 'error' => 'No hay propiedades activas en la zona'];
        }

        $existing = PriceMarketAnalysis::currentForZone($zoneKey, $this->propertyTypeSlug($anchor));

        if (! $force && ! $this->isStale($existing)) {
            return ['status' => 'skipped', 'analysis' => $existing, 'error' => null];
        }

        $startedAt = microtime(true);

        $result = $this->gemini->searchGrounded($this->buildPrompt($anchor), [
            'maxOutputTokens' => (int) config('global.PRICE_MARKET_MAX_OUTPUT_TOKENS', 4000),
            'timeout' => (int) config('global.PRICE_MARKET_TIMEOUT', 120),
        ]);

        if (empty($result['success'])) {
            $error = (string) ($result['error'] ?? 'Error desconocido');

            Log::error('PriceMarketAnalysis: no se pudo analizar la zona '.$zoneKey, [
                'error' => $error,
                'error_code' => $result['error_code'] ?? null,
            ]);

            $this->recordFailure($zoneKey, $anchor, $error);

            return ['status' => 'failed', 'analysis' => $existing, 'error' => $error];
        }

        $payload = $this->parsePayload((string) ($result['text'] ?? ''));
        $comparables = $this->normalizeComparables((array) ($payload['comparables'] ?? []));
        $sources = $this->normalizeSources($result['sources'] ?? []);

        $stats = $this->computeStats($comparables, $anchor, count($sources));
        $status = $stats['comparable_count'] >= $this->minComparables() ? 'completed' : 'partial';

        $analysis = PriceMarketAnalysis::create([
            'zone_key' => $zoneKey,
            'latitude' => $this->coordinatesOf($anchor)['lat'] ?? 0,
            'longitude' => $this->coordinatesOf($anchor)['lng'] ?? 0,
            'radius_km' => $this->radiusKm(),
            'zone_label' => $this->zoneLabelFor($anchor),
            'property_type' => $this->propertyTypeSlug($anchor),
            'transaction_type' => $this->transactionTypeOf($anchor),
            'status' => $status,
            'error_message' => $status === 'partial'
                ? 'Se obtuvieron '.$stats['comparable_count'].' comparables de '.$this->minComparables().' requeridos.'
                : null,
            'median_price' => $stats['median_price'],
            'average_price' => $stats['average_price'],
            'price_per_sqm' => $stats['price_per_sqm'],
            'price_per_sqm_min' => $stats['price_per_sqm_min'],
            'price_per_sqm_max' => $stats['price_per_sqm_max'],
            'min_price' => $stats['min_price'],
            'max_price' => $stats['max_price'],
            'market_condition' => $this->normalizeMarketCondition($payload['market_condition'] ?? null),
            'comparable_count' => $stats['comparable_count'],
            'internal_comparable_count' => $this->internalComparableCount($zoneKey),
            'comparables' => $comparables,
            'sources' => $sources,
            'summary' => trim((string) ($payload['summary'] ?? '')) ?: null,
            'confidence_score' => $stats['confidence_score'],
            'confidence_level' => $stats['confidence_level'],
            'model' => $this->currentModelName(),
            'data_fetched_at' => now(),
            'next_refresh_at' => now()->addDays($this->refreshIntervalDays()),
            'is_current' => $stats['comparable_count'] > 0,
            'algorithm_version' => self::ALGORITHM_VERSION,
        ]);

        // Sólo el snapshot con comparables válidos queda vigente; los
        // anteriores pasan a histórico.
        PriceMarketAnalysis::where('zone_key', $zoneKey)
            ->where('id', '!=', $analysis->id)
            ->update(['is_current' => false]);

        Log::info('PriceMarketAnalysis: zona analizada', [
            'zone_key' => $zoneKey,
            'comparables' => $stats['comparable_count'],
            'sources' => count($sources),
            'confidence' => $stats['confidence_score'],
            'seconds' => round(microtime(true) - $startedAt, 1),
        ]);

        return ['status' => $status, 'analysis' => $analysis, 'error' => null];
    }

    /**
     * Refresca todas las zonas con propiedades en venta activas.
     *
     * @return array Resumen por zona
     */
    public function refreshAllZones(bool $force = false, ?string $onlyZone = null, int $limit = 0): array
    {
        $zones = $this->activeZones();

        if ($onlyZone) {
            $zones = array_filter($zones, fn ($zone) => $zone['zone_key'] === $onlyZone);
        }

        if ($limit > 0) {
            $zones = array_slice($zones, 0, $limit);
        }

        $summary = [
            'total' => count($zones),
            'completed' => 0,
            'partial' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        foreach ($zones as $zone) {
            $result = $this->refreshZone($zone['zone_key'], $force);

            $status = $result['status'];
            if (isset($summary[$status])) {
                $summary[$status]++;
            }

            $summary['details'][] = [
                'zone_key' => $zone['zone_key'],
                'label' => $zone['label'],
                'properties' => $zone['properties'],
                'status' => $status,
                'comparables' => $result['analysis']->comparable_count ?? 0,
                'error' => $result['error'],
            ];
        }

        return $summary;
    }

    /**
     * Zonas activas (propiedades en venta o alquiler aprobadas) agrupadas.
     */
    public function activeZones(): array
    {
        $properties = Property::query()
            ->where('status', 1)
            ->where('request_status', 'approved')
            ->where('price', '>', 0)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0)
            ->orderBy('id')
            ->get(['id', 'title', 'city', 'state', 'address', 'latitude', 'longitude', 'propery_type', 'category_id']);

        $zones = [];

        foreach ($properties as $property) {
            $zoneKey = $this->zoneKeyForProperty($property);

            if (! $zoneKey) {
                continue;
            }

            if (! isset($zones[$zoneKey])) {
                $zones[$zoneKey] = [
                    'zone_key' => $zoneKey,
                    'label' => $this->zoneLabelFor($property),
                    'properties' => 0,
                    'property_ids' => [],
                ];
            }

            $zones[$zoneKey]['properties']++;
            $zones[$zoneKey]['property_ids'][] = $property->id;
        }

        return array_values($zones);
    }

    /**
     * Propiedad ancla de la zona: la más cercana al centroide.
     */
    public function anchorPropertyForZone(string $zoneKey): ?Property
    {
        $bounds = $this->zoneBounds($zoneKey);

        if (! $bounds) {
            return null;
        }

        $properties = Property::query()
            ->with('category')
            ->where('status', 1)
            ->where('request_status', 'approved')
            ->where('price', '>', 0)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '>=', $bounds['min_lat'])
            ->where('latitude', '<', $bounds['max_lat'])
            ->where('longitude', '>=', $bounds['min_lng'])
            ->where('longitude', '<', $bounds['max_lng'])
            ->orderBy('id')
            ->get();

        if ($properties->isEmpty()) {
            return null;
        }

        $centerLat = ($bounds['min_lat'] + $bounds['max_lat']) / 2;
        $centerLng = ($bounds['min_lng'] + $bounds['max_lng']) / 2;

        return $properties->sortBy(function ($property) use ($centerLat, $centerLng) {
            $lat = (float) $property->latitude;
            $lng = (float) $property->longitude;

            return sqrt(pow($lat - $centerLat, 2) + pow($lng - $centerLng, 2));
        })->first();
    }

    /**
     * Nº de propiedades propias (base de datos) dentro de la zona.
     */
    protected function internalComparableCount(string $zoneKey): int
    {
        $bounds = $this->zoneBounds($zoneKey);

        if (! $bounds) {
            return 0;
        }

        return Property::query()
            ->where('status', 1)
            ->where('request_status', 'approved')
            ->where('price', '>', 0)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '>=', $bounds['min_lat'])
            ->where('latitude', '<', $bounds['max_lat'])
            ->where('longitude', '>=', $bounds['min_lng'])
            ->where('longitude', '<', $bounds['max_lng'])
            ->count();
    }

    /**
     * Registra un fallo sin invalidar el snapshot vigente anterior.
     */
    protected function recordFailure(string $zoneKey, Property $anchor, string $error): void
    {
        try {
            PriceMarketAnalysis::create([
                'zone_key' => $zoneKey,
                'latitude' => $this->coordinatesOf($anchor)['lat'] ?? 0,
                'longitude' => $this->coordinatesOf($anchor)['lng'] ?? 0,
                'radius_km' => $this->radiusKm(),
                'zone_label' => $this->zoneLabelFor($anchor),
                'property_type' => $this->propertyTypeSlug($anchor),
                'transaction_type' => $this->transactionTypeOf($anchor),
                'status' => 'failed',
                'error_message' => mb_substr($error, 0, 2000),
                'is_current' => false,
                'algorithm_version' => self::ALGORITHM_VERSION,
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar el fallo del análisis de zona: '.$e->getMessage());
        }
    }

    /* -----------------------------------------------------------------
     |  Prompt y parseo
     | ----------------------------------------------------------------- */

    /**
     * Prompt de búsqueda web para una zona.
     */
    public function buildPrompt(Property $property): string
    {
        $intelligence = app(PriceIntelligenceService::class);
        $metrics = $intelligence->getPropertyMetrics($property);
        $coords = $this->coordinatesOf($property) ?: ['lat' => 0, 'lng' => 0];
        $min = $this->minComparables();
        $radius = $this->radiusKm();

        $address = trim((string) ($property->address ?: ''));
        $city = trim((string) ($property->city ?: ''));
        $state = trim((string) ($property->state ?: ''));
        $country = trim((string) ($property->country ?: 'República Dominicana'));
        $type = $this->propertyTypeLabel($property);

        $prompt = "Eres un analizador del mercado inmobiliario de República Dominicana.\n";
        $prompt .= "Necesito un análisis REAL y verificable de una zona, con precios tomados de la web.\n\n";
        $prompt .= "ZONA DE ESTUDIO\n";
        $prompt .= '- Ubicación: '.($address !== '' ? $address.', ' : '').$city.', '.$state.', '.$country."\n";
        $prompt .= '- Coordenadas: '.$coords['lat'].', '.$coords['lng']."\n";
        $prompt .= '- Radio de búsqueda: '.$radius." km alrededor de esas coordenadas\n";
        $prompt .= '- Tipo de propiedad: '.$type."\n";
        $prompt .= '- Características de referencia: '.$metrics['area']." m² de construcción, ".$metrics['bedrooms'].' dormitorios, '.$metrics['bathrooms']." baños\n\n";
        $prompt .= "TAREA\n";
        $prompt .= '1. Usa la búsqueda web para localizar al menos '.$min.' propiedades EN VENTA del mismo tipo y en esa zona (dentro del radio o en la localidad inmediatamente cercana).'."\n";
        $prompt .= "2. Prioriza portales inmobiliarios reales del país (Encuentra24, MetroEighty, Bienes Raíces, Clasificados, realtor.com, zillow) y páginas de agencias.\n";
        $prompt .= "3. Para cada propiedad registra: título, precio, área en m², dormitorios, baños y la URL exacta de la fuente.\n";
        $prompt .= "4. Si una fuente no indica el área o los dormitorios, deja ese campo en null. NO inventes datos.\n";
        $prompt .= "5. Ignora propiedades de alquiler, terreno y proyectos de desarrollo.\n\n";
        $prompt .= "Responde EXCLUSIVAMENTE con un objeto JSON válido, sin markdown y sin texto antes ni después:\n";
        $prompt .= "{\n";
        $prompt .= "  \"comparables\": [\n";
        $prompt .= "    {\"title\": \"...\", \"price\": 0, \"currency\": \"DOP\", \"area_sqm\": 0, \"bedrooms\": 0, \"bathrooms\": 0, \"url\": \"https://...\", \"source\": \"nombre del portal\"}\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"market_condition\": \"hot|balanced|slow\",\n";
        $prompt .= "  \"summary\": \"2-3 frases en español: rango de precios de la zona, precio por m2 y tendencia\"\n";
        $prompt .= "}\n\n";
        $prompt .= 'Reglas de formato: los precios van en la moneda indicada en "currency" (DOP o USD); si el anuncio está en dólares, pon currency="USD" y el precio en dólares. Usa números sin símbolos ni separadores de miles en el campo price. '.$min.' comparables como mínimo.';

        return $prompt;
    }

    /**
     * Extrae el JSON de la respuesta (tolera markdown y texto alrededor).
     */
    public function parsePayload(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/```\s*$/', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        if ($start === false) {
            return [];
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }
            if ($char === '{') {
                $depth++;
                continue;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $parsed = json_decode(substr($text, $start, $i - $start + 1), true);
                    if (is_array($parsed)) {
                        return $parsed;
                    }
                    break;
                }
            }
        }

        return [];
    }

    /**
     * Normaliza los comparables devueltos por el modelo.
     */
    public function normalizeComparables(array $raw): array
    {
        $result = [];
        $seen = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $price = $this->extractPrice($item['price'] ?? null);
            if ($price <= 0) {
                continue;
            }

            $url = $this->extractUrl($item['url'] ?? ($item['link'] ?? null));
            $title = trim((string) ($item['title'] ?? ''));
            $source = trim((string) ($item['source'] ?? ($item['portal'] ?? '')));

            if ($source === '' && $url !== '') {
                $source = (string) (parse_url($url, PHP_URL_HOST) ?: '');
            }

            $area = $this->extractNumber($item['area_sqm'] ?? ($item['area'] ?? ($item['size'] ?? null)));
            $bedrooms = $this->extractNumber($item['bedrooms'] ?? ($item['rooms'] ?? null));
            $bathrooms = $this->extractNumber($item['bathrooms'] ?? null);

            $dedupe = $url !== '' ? mb_strtolower($url) : md5(mb_strtolower($title).'|'.$price.'|'.$area);

            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

            $result[] = [
                'title' => $title !== '' ? $title : 'Propiedad en '.($source !== '' ? $source : 'la zona'),
                'price' => round($price, 2),
                'currency' => $this->normalizeCurrency($item['currency'] ?? 'DOP'),
                'area_sqm' => $area > 0 ? round($area, 2) : null,
                'bedrooms' => $bedrooms > 0 ? (int) round($bedrooms) : null,
                'bathrooms' => $bathrooms > 0 ? (int) round($bathrooms) : null,
                'url' => $url,
                'source' => $source,
            ];
        }

        return $result;
    }

    /**
     * Normaliza las fuentes web (deduplicadas por URI).
     */
    public function normalizeSources(array $raw): array
    {
        $result = [];
        $seen = [];

        foreach ($raw as $source) {
            if (! is_array($source)) {
                continue;
            }

            $uri = trim((string) ($source['uri'] ?? ($source['url'] ?? ($source['web']['uri'] ?? ''))));
            if ($uri === '') {
                continue;
            }

            // Si solo hay chunks de grounding sin URL limpia, intentar extraer
            $title = trim((string) ($source['title'] ?? ($source['web']['title'] ?? '')));
            $domain = trim((string) ($source['domain'] ?? ''));

            if ($domain === '' && preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', $title)) {
                $domain = $title;
            }

            $key = $domain !== '' ? mb_strtolower($domain) : $uri;

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $result[] = [
                'title' => $title !== '' ? $title : ($domain !== '' ? $domain : $uri),
                'uri' => $uri,
                'domain' => $domain !== '' ? $domain : (string) (parse_url($uri, PHP_URL_HOST) ?: ''),
                'accessed_at' => now()->toIso8601String(),
            ];
        }

        return $result;
    }

    /* -----------------------------------------------------------------
     |  Estadística de la zona
     | ----------------------------------------------------------------- */

    /**
     * Calcula las estadísticas de mercado a partir de los comparables web.
     */
    public function computeStats(array $comparables, Property $anchor, int $sourceCount = 0): array
    {
        $intelligence = app(PriceIntelligenceService::class);
        $metrics = $intelligence->getPropertyMetrics($anchor);

        $prices = [];
        $perSqm = [];
        $withArea = 0;

        foreach ($comparables as $comparable) {
            $base = $intelligence->toBaseCurrency($comparable['price'], $comparable['currency'] ?: 'DOP');

            if ($base <= 0) {
                continue;
            }

            $prices[] = $base;

            if (! empty($comparable['area_sqm']) && $comparable['area_sqm'] > 0) {
                $perSqm[] = [
                    'value' => $base / $comparable['area_sqm'],
                    'bedrooms' => $comparable['bedrooms'],
                    'area' => $comparable['area_sqm'],
                ];
                $withArea++;
            }
        }

        if (empty($prices)) {
            return [
                'median_price' => null,
                'average_price' => null,
                'price_per_sqm' => null,
                'price_per_sqm_min' => null,
                'price_per_sqm_max' => null,
                'min_price' => null,
                'max_price' => null,
                'comparable_count' => count($comparables),
                'confidence_score' => 0,
                'confidence_level' => 'low',
            ];
        }

        $medianPrice = $this->median($prices);
        $averagePrice = array_sum($prices) / count($prices);

        $values = array_column($perSqm, 'value');
        sort($values);

        $medianPerSqm = $this->median($values);
        $p25 = $this->percentile($values, 25);
        $p75 = $this->percentile($values, 75);

        // Ajuste por número de dormitorios: si hay comparables similares se
        // usa su mediana, si no se mantiene la mediana general de la zona.
        $adjustment = 1.0;
        $targetBedrooms = (float) $metrics['bedrooms'];
        $similar = [];

        if ($targetBedrooms > 0) {
            foreach ($perSqm as $row) {
                if (! empty($row['bedrooms']) && abs((float) $row['bedrooms'] - $targetBedrooms) <= 1) {
                    $similar[] = $row['value'];
                }
            }
        }

        if (! empty($similar) && $medianPerSqm > 0) {
            $adjustment = max(0.8, min(1.2, $this->median($similar) / $medianPerSqm));
        }

        $referencePerSqm = $medianPerSqm * $adjustment;
        $area = (float) $metrics['area'];

        $confidence = $this->computeConfidence(count($comparables), $withArea, count($similar), $sourceCount);

        return [
            'median_price' => round($medianPrice, 2),
            'average_price' => round($averagePrice, 2),
            'price_per_sqm' => round($referencePerSqm, 2),
            'price_per_sqm_min' => round($p25 * $adjustment, 2),
            'price_per_sqm_max' => round($p75 * $adjustment, 2),
            'min_price' => $area > 0 ? round($p25 * $adjustment * $area, 2) : round($medianPrice * 0.9, 2),
            'max_price' => $area > 0 ? round($p75 * $adjustment * $area, 2) : round($medianPrice * 1.1, 2),
            'comparable_count' => count($comparables),
            'confidence_score' => $confidence['score'],
            'confidence_level' => $confidence['level'],
        ];
    }

    /**
     * Nivel de confianza según la evidencia disponible.
     *
     * Base 20 · +3 por comparable con área (máx 30) · +15 si hay 10 o más
     * comparables con área · +10 si hay comparables con los mismos dormitorios
     * · +15 si se alcanzó el mínimo de comparables · +10 si hay fuentes
     * web distintas citadas.
     */
    protected function computeConfidence(int $comparables, int $withArea, int $similarBedrooms, int $sourceCount): array
    {
        $score = 20.0;
        $score += min(30, $withArea * 3);
        $score += $withArea >= 10 ? 15 : 0;
        $score += $similarBedrooms >= 3 ? 10 : 0;
        $score += $comparables >= $this->minComparables() ? 15 : 0;
        $score += $sourceCount >= 3 ? 10 : 0;

        $score = max(0, min(100, $score));

        $level = 'low';
        if ($score >= 70) {
            $level = 'high';
        } elseif ($score >= 45) {
            $level = 'medium';
        }

        return ['score' => round($score, 2), 'level' => $level];
    }

    /* -----------------------------------------------------------------
     |  Helpers
     | ----------------------------------------------------------------- */

    protected function median(array $values): float
    {
        $values = array_values(array_filter($values, fn ($value) => is_numeric($value)));
        sort($values);

        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return (float) $values[$middle];
    }

    /**
     * Percentil (interpolación lineal) sobre un array ya ordenado.
     */
    protected function percentile(array $sorted, float $percentile): float
    {
        $count = count($sorted);

        if ($count === 0) {
            return 0.0;
        }
        if ($count === 1) {
            return (float) $sorted[0];
        }

        $position = ($percentile / 100) * ($count - 1);
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        if ($lower === $upper) {
            return (float) $sorted[$lower];
        }

        $weight = $position - $lower;

        return $sorted[$lower] * (1 - $weight) + $sorted[$upper] * $weight;
    }

    /**
     * Extrae un precio numérico desde texto libre ("RD$ 2,500,000").
     */
    protected function extractPrice($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.,]/', '', $value);

        if ($clean === '' || $clean === '.' || $clean === ',') {
            return 0.0;
        }

        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $separator = max($lastComma, $lastDot);
            $decimals = strlen(substr($clean, $separator + 1));

            if ($decimals === 3) {
                $clean = preg_replace('/[.,]/', '', $clean);
            } elseif ($decimals > 0) {
                $clean = str_replace([',', '.'], ['.', ''], $clean);
            } else {
                $clean = preg_replace('/[.,]/', '', $clean);
            }
        } elseif ($lastComma !== false) {
            $decimals = strlen(substr($clean, $lastComma + 1));
            $clean = $decimals === 3 ? str_replace(',', '', $clean) : str_replace(',', '.', $clean);
        } elseif ($lastDot !== false) {
            $decimals = strlen(substr($clean, $lastDot + 1));
            $clean = $decimals === 3 ? str_replace('.', '', $clean) : $clean;
        }

        return (float) $clean;
    }

    protected function extractNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.,]/', '', $value);

        if ($clean === '') {
            return 0.0;
        }

        if (str_contains($clean, ',') && ! str_contains($clean, '.')) {
            $decimals = strlen(substr($clean, strrpos($clean, ',') + 1));
            $clean = $decimals === 3 ? str_replace(',', '', $clean) : str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        return (float) $clean;
    }

    protected function extractUrl($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = trim($value);

        if ($value === '' || ! str_contains($value, 'http')) {
            return '';
        }

        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.ltrim($value, '/');
        }

        return $value;
    }

    protected function normalizeCurrency($value): string
    {
        $currency = strtoupper(trim((string) $value));
        $currency = str_replace(['$', ' ', '.'], '', $currency);

        if (in_array($currency, ['US', 'USD', 'DOLLAR', 'DOLLARS'], true)) {
            return 'USD';
        }
        if (in_array($currency, ['RD', 'DOP', 'DOPES', 'PESOS', 'PESO'], true) || $currency === '') {
            return 'DOP';
        }

        return in_array($currency, ['EUR', 'GBP', 'CAD', 'MXN']) ? $currency : 'DOP';
    }

    protected function normalizeMarketCondition($value): string
    {
        $value = strtolower(trim((string) $value));

        return match ($value) {
            'hot', 'hot_market', 'caliente' => 'hot_market',
            'slow', 'slow_market', 'frio', 'frío' => 'slow_market',
            default => 'balanced',
        };
    }

    public function propertyTypeSlug(Property $property): ?string
    {
        $category = $property->category;

        return $category ? ($category->slug_id ?: $category->category) : 'other';
    }

    protected function propertyTypeLabel(Property $property): string
    {
        $category = $property->category;

        if ($category) {
            $name = trim((string) ($category->category ?: ''));
            if ($name !== '') {
                return $name;
            }
        }

        return 'casa o apartamento';
    }

    protected function transactionTypeOf(Property $property): string
    {
        return in_array((int) $property->getRawOriginal('propery_type'), [1, 3]) ? 'rental' : 'sale';
    }

    protected function currentModelName(): string
    {
        $url = (string) config('services.gemini.api_url');

        if (preg_match('#/models/([^:/?]+)#', $url, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
