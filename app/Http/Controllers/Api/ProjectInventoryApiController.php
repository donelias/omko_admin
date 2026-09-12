<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectUnitInventoryMovement;
use App\Models\Property;
use App\Models\Projects;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Inventario on-plan (FASE 8, T2).
 * Operaciones sobre unidades de propiedades on-plan (is_project_unit = 1)
 * con trazabilidad completa en project_unit_inventory_movements.
 */
class ProjectInventoryApiController extends Controller
{
    /**
     * Reserva N unidades de una propiedad on-plan.
     * POST /api/project-inventory/reserve
     */
    public function reserve(Request $request)
    {
        return $this->handleMove($request, ProjectUnitInventoryMovement::RESERVE);
    }

    /**
     * Confirma una reserva (convierte reservadas en vendidas).
     * POST /api/project-inventory/confirm
     */
    public function confirm(Request $request)
    {
        return $this->handleMove($request, ProjectUnitInventoryMovement::CONFIRM);
    }

    /**
     * Cancela/libera unidades reservadas.
     * POST /api/project-inventory/cancel
     */
    public function cancel(Request $request)
    {
        return $this->handleMove($request, ProjectUnitInventoryMovement::CANCEL);
    }

    /**
     * Ajuste manual de unidades.
     * POST /api/project-inventory/adjust
     */
    public function adjust(Request $request)
    {
        return $this->handleMove($request, ProjectUnitInventoryMovement::MANUAL_ADJUST);
    }

    /**
     * Historial de movimientos de una propiedad on-plan.
     * GET /api/project-inventory/movements?property_id=X
     */
    public function movements(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $movements = ProjectUnitInventoryMovement::where('property_id', $request->property_id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'error' => false,
            'data' => $movements,
        ]);
    }

    /**
     * Estado actual del inventario de una propiedad on-plan.
     * GET /api/project-inventory/status?property_id=X
     */
    public function status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $property = Property::find($request->property_id);

        if (! $property) {
            return response()->json(['error' => true, 'message' => 'Propiedad no encontrada'], 404);
        }

        if (! $property->is_project_unit) {
            return response()->json(['error' => true, 'message' => 'La propiedad no es una unidad on-plan'], 422);
        }

        return response()->json([
            'error' => false,
            'data' => [
                'property_id' => $property->id,
                'title' => $property->title,
                'unit_code' => $property->unit_code,
                'total_units' => (int) $property->total_units,
                'available_units' => (int) $property->available_units,
                'reserved_units' => (int) $property->reserved_units,
                'sold_units' => (int) $property->sold_units,
                'unit_status' => $property->unit_status,
            ],
        ]);
    }

    protected function handleMove(Request $request, string $eventType)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|integer',
            'delta_units' => 'required|integer|min:1',
            'appointment_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $property = Property::find($request->property_id);

        if (! $property) {
            return response()->json(['error' => true, 'message' => 'Propiedad no encontrada'], 404);
        }

        if (! $property->is_project_unit) {
            return response()->json(['error' => true, 'message' => 'La propiedad no es una unidad on-plan'], 422);
        }

        $delta = (int) $request->delta_units;

        return DB::transaction(function () use ($property, $delta, $eventType, $request) {
            $available = (int) $property->available_units;
            $reserved = (int) $property->reserved_units;
            $sold = (int) $property->sold_units;

            switch ($eventType) {
                case ProjectUnitInventoryMovement::RESERVE:
                    if ($delta > $available) {
                        return response()->json([
                            'error' => true,
                            'message' => 'Unidades disponibles insuficientes (disponibles: '.$available.')',
                        ], 409);
                    }
                    $available -= $delta;
                    $reserved += $delta;
                    break;

                case ProjectUnitInventoryMovement::CONFIRM:
                    if ($delta > $reserved) {
                        return response()->json([
                            'error' => true,
                            'message' => 'No hay suficientes unidades reservadas para confirmar',
                        ], 409);
                    }
                    $reserved -= $delta;
                    $sold += $delta;
                    break;

                case ProjectUnitInventoryMovement::CANCEL:
                    if ($delta > $reserved) {
                        return response()->json([
                            'error' => true,
                            'message' => 'No hay suficientes unidades reservadas para cancelar',
                        ], 409);
                    }
                    $reserved -= $delta;
                    $available += $delta;
                    break;

                case ProjectUnitInventoryMovement::MANUAL_ADJUST:
                    $available += $delta;
                    break;

                default:
                    return response()->json(['error' => true, 'message' => 'Evento no soportado'], 422);
            }

            $before = (int) $property->available_units;

            $property->forceFill([
                'available_units' => max($available, 0),
                'reserved_units' => max($reserved, 0),
                'sold_units' => max($sold, 0),
                'unit_status' => $this->deriveStatus((int) $property->total_units, (int) $available),
            ])->save();

            ProjectUnitInventoryMovement::create([
                'property_id' => $property->id,
                'project_id' => $property->project_id ?: $this->inferProject($property),
                'event_type' => $eventType,
                'delta_units' => $eventType === ProjectUnitInventoryMovement::MANUAL_ADJUST ? $delta : $delta,
                'before_units' => $before,
                'after_units' => max($available, 0),
                'appointment_id' => $request->appointment_id,
                'actor_type' => 'customer',
                'actor_id' => Auth::id(),
                'notes' => $request->notes,
            ]);

            return response()->json([
                'error' => false,
                'message' => 'Inventario actualizado ('.$eventType.')',
                'data' => [
                    'property_id' => $property->id,
                    'available_units' => max($available, 0),
                    'reserved_units' => max($reserved, 0),
                    'sold_units' => max($sold, 0),
                    'unit_status' => $this->deriveStatus((int) $property->total_units, (int) $available),
                ],
            ]);
        });
    }

    protected function deriveStatus(?int $total, int $available): string
    {
        if ((int) $total === 0) {
            return 'inactive';
        }

        $percent = floor(($available / (int) $total) * 100);

        if ($available <= 0) {
            return 'sold_out';
        }

        if ($percent <= 20) {
            return 'low_stock';
        }

        return 'available';
    }

    protected function inferProject(Property $property): ?int
    {
        // Si la unidad pertenece a un proyecto por nombre/relación, se puede inferir.
        // Por ahora devuelve null; queda señalado para mejora futura.
        return $property->project_id;
    }
}
