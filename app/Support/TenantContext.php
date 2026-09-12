<?php

namespace App\Support;

use App\Models\Agency;

/**
 * Contexto de marca/tenant por request (FASE 8 - T5 multi-marca).
 *
 * TenantMiddleware llama a setAgency() en el grupo api; si el middleware no
 * corrió (panel admin web, artisan, queue) isActive() es false y los scopes
 * de tenancy no filtran nada.
 */
class TenantContext
{
    protected static bool $active = false;

    protected static ?Agency $agency = null;

    public static function setAgency(?Agency $agency): void
    {
        static::$active = true;
        static::$agency = $agency;
    }

    public static function isActive(): bool
    {
        return static::$active;
    }

    public static function agency(): ?Agency
    {
        if (! static::$active) {
            return null;
        }

        return static::$agency;
    }

    public static function agencyId(): ?int
    {
        return static::agency()?->id;
    }
}