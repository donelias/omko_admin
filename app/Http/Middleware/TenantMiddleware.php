<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * FASE 8 (T5 multi-marca).
 *
 * Resuelve la agencia/marca del request en el grupo api:
 *   1. Header X-Brand (slug) — enviado por el frontend Next (ya resuelto por host/?brand=).
 *   2. Host configurado en agencies.domains (coincidencia exacta).
 * Default: sin marca (dominio principal -> solo datos globales).
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        TenantContext::setAgency($this->resolveAgency($request));
        $request->merge(['brand_agency_id' => TenantContext::agencyId()]);

        return $next($request);
    }

    protected function resolveAgency(Request $request): ?Agency
    {
        $brand = strtolower(trim((string) $request->header('X-Brand', '')));
        if ($brand !== '') {
            $agency = Cache::remember("agency_slug_{$brand}", 3600, fn () => Agency::where('slug', $brand)->first());

            return $agency && $agency->status ? $agency : null;
        }

        $host = strtolower(trim((string) $request->getHost()));
        if ($host === '') {
            return null;
        }

        $agency = Cache::remember("agency_host_{$host}", 3600, function () use ($host) {
            return Agency::active()->get()->first(
                fn (Agency $a) => in_array($host, $a->domain_list, true)
            );
        });

        if ($agency) {
            return $agency;
        }

        // Subdominio por slug: agencia.<dominio-base>
        $base = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        if ($base !== '' && str_ends_with($host, '.'.$base)) {
            $sub = substr($host, 0, strlen($host) - strlen($base) - 1);
            if ($sub !== '' && $sub !== 'www') {
                $agency = Cache::remember("agency_slug_{$sub}", 3600, fn () => Agency::where('slug', $sub)->first());

                return $agency && $agency->status ? $agency : null;
            }
        }

        return null;
    }
}