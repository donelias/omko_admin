<?php

namespace App\Traits;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * FASE 8 (T5 multi-marca).
 *
 * Aísla el modelo por agencia cuando el request proviene del grupo api
 * (TenantMiddleware activo). Regla:
 *  - Sin marca (dominio principal): solo registros con agency_id NULL (globales).
 *  - Marca X: registros globales (NULL) + registros de la marca X.
 */
trait HasTenantFilter
{
    protected static function bootHasTenantFilter(): void
    {
        static::addGlobalScope('agency_tenant', function (Builder $builder) {
            if (! TenantContext::isActive()) {
                return;
            }

            $tenantColumn = static::$tenantColumn ?? 'agency_id';
            $agencyId = TenantContext::agencyId();

            if ($agencyId === null) {
                $builder->whereNull($tenantColumn);
            } else {
                $builder->where(function (Builder $q) use ($tenantColumn, $agencyId) {
                    $q->whereNull($tenantColumn)->orWhere($tenantColumn, $agencyId);
                });
            }
        });
    }
}