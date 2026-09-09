<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gestión de tasas de cambio "del día vigente" para el motor de precios.
 *
 * Orden de resolución:
 *  1. Caché (TTL corto) si todavía es reciente.
 *  2. Valor persistido en settings (type=price_exchange_rates) si su as_of es de hoy.
 *  3. Descarga desde el proveedor configurado (google | bcrd | allratestoday) y lo persiste.
 *  4. Valor persistido en settings aunque no sea de hoy (última conocida).
 *  5. Fallback estático config('global.PRICE_EXCHANGE_RATES').
 */
class ExchangeRateService
{
    protected const CACHE_KEY = 'price_exchange_rates_meta';

    /**
     * Tasas activas (mapa moneda => factor hacia la moneda base).
     */
    public function rates(): array
    {
        $meta = $this->ratesWithMeta();

        return $meta['rates'] ?? [];
    }

    /**
     * Tasas + metadatos (base, as_of, source, fetched_at).
     */
    public function ratesWithMeta(): array
    {
        if ($cached = Cache::get(self::CACHE_KEY)) {
            return $cached;
        }

        $meta = $this->resolve();

        Cache::put(self::CACHE_KEY, $meta, (int) config('global.PRICE_EXCHANGE_RATES_CACHE_TTL', 21600));

        return $meta;
    }

    /**
     * Fuerza la descarga/persistencia de tasas frescas si el proveedor lo permite.
     */
    public function refresh(): array
    {
        $source = strtolower((string) config('global.PRICE_EXCHANGE_RATES_SOURCE', 'bcrd'));

        if ($source === 'static') {
            return $this->metaFromConfig();
        }

        $fetched = $this->fetchFromProvider();

        if ($fetched) {
            $this->persist($fetched['rates'], $fetched['as_of'], $fetched['source']);
            Cache::forget(self::CACHE_KEY);
        }

        return $this->ratesWithMeta();
    }

    /**
     * Guarda manualmente la tasa (p. ej. desde admin o comando --set-usd).
     */
    public function setManual(float $usdRate, ?string $asOf = null): array
    {
        $rates = array_merge($this->fallbackRates(), ['USD' => $usdRate]);
        $asOf = $asOf ?: Carbon::now()->toDateString();

        $meta = [
            'rates' => $rates,
            'base_currency' => $this->baseCurrency(),
            'as_of' => $asOf,
            'source' => 'setting',
            'fetched_at' => Carbon::now()->toDateTimeString(),
        ];

        $this->persist($rates, $asOf, 'setting');
        Cache::forget(self::CACHE_KEY);

        return $meta;
    }

    /**
     * ------------------------------------------------------------------
     * Internos
     * ------------------------------------------------------------------
     */

    protected function resolve(): array
    {
        $source = strtolower((string) config('global.PRICE_EXCHANGE_RATES_SOURCE', 'bcrd'));

        if ($source === 'static') {
            return $this->metaFromConfig();
        }

        $stored = $this->fromSetting();

        if ($stored && $stored['as_of'] === Carbon::now()->toDateString()) {
            return $stored;
        }

        $fetched = $this->fetchFromProvider();

        if ($fetched) {
            $this->persist($fetched['rates'], $fetched['as_of'], $fetched['source']);

            return [
                'rates' => $fetched['rates'],
                'base_currency' => $this->baseCurrency(),
                'as_of' => $fetched['as_of'],
                'source' => $fetched['source'],
                'fetched_at' => Carbon::now()->toDateTimeString(),
            ];
        }

        if ($stored) {
            return $stored;
        }

        return $this->metaFromConfig();
    }

    protected function fetchFromProvider(): ?array
    {
        $source = strtolower((string) config('global.PRICE_EXCHANGE_RATES_SOURCE', 'bcrd'));

        try {
            if ($source === 'google') {
                $data = $this->fetchGoogle();

                return $this->normalizeGoogle($data);
            }

            if ($source === 'bcrd') {
                $data = $this->fetchBcrd();

                return $this->normalizeBcrd($data);
            }

            if ($source === 'allratestoday') {
                $data = $this->fetchAllRatesToday();

                return $this->normalizeAllRatesToday($data);
            }

            return null;
        } catch (Throwable $e) {
            logger()->warning('ExchangeRateService: fallo al descargar tasas. '.$e->getMessage());

            return null;
        }
    }

    protected function fetchBcrd(): ?array
    {
        $endpoint = (string) config('global.PRICE_EXCHANGE_RATES_API');
        $rangeDays = (int) config('global.PRICE_EXCHANGE_RATES_RANGE_DAYS', 10);

        $since = Carbon::now()->subDays($rangeDays)->format('Y/m/d');
        $until = Carbon::now()->format('Y/m/d');

        $response = Http::timeout(12)
            ->acceptJson()
            ->post($endpoint, [
                'tipo_operacion' => 'N',
                'fecha_desde' => $since,
                'fecha_hasta' => $until,
                'soloTipoPago' => false,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Descarga la página pública de Google Finance (USD/DOP) y la devuelve como HTML.
     * Google no ofrece API pública: es scraping de https://www.google.com/finance/quote/USD-DOP
     */
    protected function fetchGoogle(): ?string
    {
        $endpoint = (string) config('global.PRICE_EXCHANGE_RATES_API');
        $useGoogle = str_contains($endpoint, 'google.com');
        if (! $useGoogle) {
            $endpoint = 'https://www.google.com/finance/quote/USD-DOP?hl=en';
        }

        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->get($endpoint);

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    protected function fetchAllRatesToday(): ?array
    {
        $endpoint = (string) config('global.PRICE_EXCHANGE_RATES_API');
        $key = (string) config('global.PRICE_EXCHANGE_RATES_API_KEY', '');

        $headers = $key !== '' ? ['Authorization' => 'Bearer '.$key] : [];

        $response = Http::timeout(12)
            ->withHeaders($headers)
            ->get($endpoint);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Parsea la respuesta del proxy BCRD (BSantander/BHD y variantes).
     */
    protected function normalizeBcrd($data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $items = $this->extractRows($data);
        if (empty($items)) {
            return null;
        }

        $today = Carbon::now();
        $cutoff = $today->copy()->subDays((int) config('global.PRICE_EXCHANGE_RATES_RANGE_DAYS', 10));
        $best = null;

        foreach ($items as $item) {
            $currency = $this->extractCurrency($item);
            if (strtoupper((string) $currency) !== 'USD') {
                continue;
            }

            $rate = $this->extractRate($item);
            $date = $this->extractDate($item);

            if ($rate <= 0 || $date === null) {
                continue;
            }

            // Preferir la fecha más reciente (el proxy devuelve histórico).
            $dateValue = Carbon::parse($date);
            $score = $dateValue->eq($today) ? 2 : ($dateValue->gte($cutoff) ? 1 : 0);
            if ($best === null || $score > $best['score'] || ($score === $best['score'] && $date > $best['date'])) {
                $best = compact('rate', 'date', 'score');
            }
        }

        if ($best === null || $best['rate'] <= 0) {
            return null;
        }

        $rates = array_merge($this->fallbackRates(), ['USD' => round($best['rate'], 4)]);

        return [
            'rates' => $rates,
            'as_of' => $best['date'],
            'source' => 'bcrd',
        ];
    }

    /**
     * Extrae la tasa USD → DOP del markup de Google Finance.
     */
    protected function normalizeGoogle($html): ?array
    {
        if (! is_string($html) || $html === '') {
            return null;
        }

        $rate = null;

        if (preg_match('/"USD \/ DOP",3,null,\[[0-9.,]+\],null,([0-9.]+),null,null,null,\[[0-9]+\]/', $html, $m)) {
            $rate = (float) $m[1];
        } elseif (preg_match('/data-last-price="([0-9.]+)"/', $html, $m)) {
            $rate = (float) $m[1];
        }

        if (! $rate || $rate <= 0) {
            return null;
        }

        $rates = array_merge($this->fallbackRates(), ['USD' => round($rate, 4)]);

        return [
            'rates' => $rates,
            'as_of' => Carbon::now()->toDateString(),
            'source' => 'google',
        ];
    }

    /**
     * Parsea la respuesta de AllRatesToday (filas por moneda).
     */
    protected function normalizeAllRatesToday($data): ?array
    {
        $rows = $this->extractRows($data);
        if (empty($rows)) {
            return null;
        }

        $usdRate = null;
        $asOf = null;

        foreach ($rows as $item) {
            $source = strtoupper((string) ($item['source'] ?? ($item['base'] ?? $item['currency'] ?? '')));
            $target = strtoupper((string) ($item['target'] ?? ($item['quote'] ?? '')));

            if ($source === 'USD' && in_array($target, ['DOP', 'RD'], true)) {
                $rate = (float) ($item['rate'] ?? 0);
                if ($rate > 0) {
                    $usdRate = $rate;
                }
            }
            if ($source === 'DOP' && $target === 'USD' && $usdRate === null) {
                $rate = (float) ($item['rate'] ?? 0);
                if ($rate > 0) {
                    $usdRate = 1 / $rate;
                }
            }

            $asOf = $asOf ?: ($item['rate_date'] ?? $item['date'] ?? null);
        }

        if ($usdRate === null) {
            return null;
        }

        $rates = array_merge($this->fallbackRates(), ['USD' => round($usdRate, 4)]);

        return [
            'rates' => $rates,
            'as_of' => $asOf ?: Carbon::now()->toDateString(),
            'source' => 'allratestoday',
        ];
    }

    /**
     * Aplana árboles JSON (el proxy a veces anida bajo "tasas" / "data" / "result").
     */
    protected function extractRows(array $data): array
    {
        $rows = [];
        $currency = $data;

        if (isset($data['tasas']) && is_array($data['tasas'])) {
            $currency = $data['tasas'];
        } elseif (isset($data['data']) && is_array($data['data'])) {
            $currency = $data['data'];
        } elseif (isset($data['result']) && is_array($data['result'])) {
            $currency = $data['result'];
        } elseif (isset($data['rates']) && is_array($data['rates'])) {
            $currency = $data['rates'];
        }

        foreach ($currency as $key => $value) {
            if (is_array($value)) {
                $rows[] = $value;
            }
        }

        return $rows;
    }

    protected function extractCurrency(array $item): string
    {
        foreach (['currency_code', 'currencyCode', 'currency', 'code', 'currency_code_a'] as $key) {
            if (isset($item[$key])) {
                return (string) $item[$key];
            }
        }

        return '';
    }

    protected function extractRate(array $item): float
    {
        foreach (['venta', 'sell', 'compra', 'buy', 'rate', 'medio', 'mid'] as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null) {
                $value = (float) $item[$key];
                if ($value > 0) {
                    return $value;
                }
            }
        }

        return 0;
    }

    protected function extractDate(array $item): ?string
    {
        foreach (['fecha', 'date', 'fecha_tasa', 'rate_date', 'pub_date'] as $key) {
            if (isset($item[$key])) {
                try {
                    return Carbon::parse($item[$key])->toDateString();
                } catch (Throwable $e) {
                    continue;
                }
            }
        }

        return null;
    }

    protected function fromSetting(): ?array
    {
        $setting = Setting::where('type', 'price_exchange_rates')->first();
        if (! $setting || empty($setting->data)) {
            return null;
        }

        $decoded = json_decode((string) $setting->data, true);
        if (! is_array($decoded) || empty($decoded['rates']) || empty($decoded['as_of'])) {
            return null;
        }

        return [
            'rates' => $decoded['rates'],
            'base_currency' => $this->baseCurrency(),
            'as_of' => $decoded['as_of'],
            'source' => $decoded['source'] ?? 'setting',
            'fetched_at' => $decoded['fetched_at'] ?? null,
        ];
    }

    protected function persist(array $rates, string $asOf, string $source): void
    {
        $payload = [
            'rates' => $rates,
            'as_of' => $asOf,
            'source' => $source,
            'fetched_at' => Carbon::now()->toDateTimeString(),
        ];

        $setting = Setting::where('type', 'price_exchange_rates')->first();
        if ($setting) {
            $setting->data = json_encode($payload);
            $setting->save();
        } else {
            Setting::create([
                'type' => 'price_exchange_rates',
                'data' => json_encode($payload),
            ]);
        }
    }

    protected function metaFromConfig(): array
    {
        return [
            'rates' => $this->fallbackRates(),
            'base_currency' => $this->baseCurrency(),
            'as_of' => Carbon::now()->toDateString(),
            'source' => 'static',
            'fetched_at' => Carbon::now()->toDateTimeString(),
        ];
    }

    protected function fallbackRates(): array
    {
        $rates = config('global.PRICE_EXCHANGE_RATES', ['DOP' => 1.0, 'USD' => 58.5]);
        $rates['DOP'] = 1.0;

        return $rates;
    }

    protected function baseCurrency(): string
    {
        return strtoupper((string) config('global.PRICE_BASE_CURRENCY', 'DOP'));
    }
}