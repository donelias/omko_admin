<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use App\Models\PropertyCommission;
use App\Models\UserPayAsYouGoCredit;
use Illuminate\Http\Request;

/**
 * Monetización avanzada (comisiones + créditos pay-as-you-go) — FASE 8 (T6).
 * Requiere autenticación Sanctum. El agente es el Customer autenticado
 * (customer_id vincula las comisiones al agente web).
 */
class CommissionApiController extends Controller
{
    /**
     * Lista las comisiones del agente autenticado.
     * GET /api/commission/my-commissions
     */
    public function myCommissions(Request $request)
    {
        $customer = $request->user();

        $commissions = PropertyCommission::with(['property', 'customer'])
            ->where('customer_id', $customer->id)
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'error' => false,
            'data' => $commissions,
        ]);
    }

    /**
     * Consulta la configuración de comisiones activa.
     * GET /api/commission/settings
     */
    public function settings()
    {
        $settings = CommissionSetting::where('is_active', 1)
            ->orderBy('id')
            ->get();

        return response()->json([
            'error' => false,
            'data' => $settings,
        ]);
    }

    /**
     * Créditos pay-as-you-go del agente autenticado.
     * GET /api/commission/my-credits
     */
    public function myCredits(Request $request)
    {
        $customer = $request->user();

        $credits = UserPayAsYouGoCredit::with('pay_as_you_go')
            ->where('user_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'error' => false,
            'data' => $credits,
        ]);
    }

    /**
     * Marca una comisión como pagada (solo si pertenece al agente autenticado).
     * POST /api/commission/mark-paid
     */
    public function markPaid(Request $request)
    {
        $request->validate([
            'commission_id' => 'required|integer',
        ]);

        $customer = $request->user();

        $commission = PropertyCommission::where('id', $request->commission_id)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $commission) {
            return response()->json([
                'error' => true,
                'message' => 'Comisión no encontrada o no pertenece al agente',
            ], 404);
        }

        $commission->payment_status = 'paid';
        $commission->save();

        return response()->json([
            'error' => false,
            'message' => 'Comisión marcada como pagada',
            'data' => $commission,
        ]);
    }
}
