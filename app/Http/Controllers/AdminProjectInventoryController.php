<?php

namespace App\Http\Controllers;

use App\Models\ProjectUnitInventoryMovement;
use App\Models\Property;
use App\Models\Projects;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminProjectInventoryController extends Controller
{
    /**
     * Listado de propiedades on-plan con inventario de unidades (FASE 8, T2).
     */
    public function index()
    {
        if (! has_permissions('read', 'project_inventory')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $projects = Projects::orderBy('title')->get();

        return view('admin.project-inventory.index', compact('projects'));
    }

    /**
     * Tabla server-side de propiedades on-plan.
     */
    public function getList(Request $request)
    {
        if (! has_permissions('read', 'project_inventory')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $search = $request->input('search');

        $sql = Property::where('is_project_unit', 1)
            ->when($request->has('search') && ! empty($search), function ($query) use ($search) {
                $query->where('title', 'LIKE', "%$search%")
                    ->orWhere('unit_code', 'LIKE', "%$search%")
                    ->orWhere('unit_status', 'LIKE', "%$search%");
            });

        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = [];
        $statusLabels = [
            'available' => ['Disponible', 'success'],
            'low_stock' => ['Pocas unidades', 'warning'],
            'sold_out' => ['Agotado', 'danger'],
            'inactive' => ['Inactivo', 'secondary'],
        ];

        foreach ($res as $row) {
            $operate = '';
            if (has_permissions('update', 'project_inventory')) {
                $operate .= BootstrapTableService::editButton('', true, '#inventoryModal', null, $row->id);
            }
            $operate .= BootstrapTableService::button(
                'bi bi-clock-history',
                route('admin.project-inventory.movements', $row->id),
                ['btn-info'],
                ['title' => 'Movimientos']
            );

            [$statusLabel, $statusColor] = $statusLabels[$row->unit_status] ?? [$row->unit_status, 'info'];

            $rows[] = [
                'id' => $row->id,
                'title' => $row->title,
                'unit_code' => $row->unit_code ?: '-',
                'project_name' => $row->project_id ? ($row->project ? $row->project->title : '-') : '-',
                'total_units' => (int) $row->total_units,
                'available_units' => (int) $row->available_units,
                'reserved_units' => (int) $row->reserved_units,
                'sold_units' => (int) $row->sold_units,
                'unit_status' => $row->unit_status,
                'status_label' => $statusLabel,
                'status_color' => $statusColor,
                'operate' => $operate,
            ];
        }

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Ajuste manual de unidades de una propiedad on-plan.
     */
    public function adjust(Request $request)
    {
        if (! has_permissions('update', 'project_inventory')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:propertys,id',
            'delta_units' => 'required|integer',
            'notes' => 'nullable|string|max:2000',
            'appointment_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $property = Property::findOrFail($request->property_id);

            if (! $property->is_project_unit) {
                ResponseService::errorResponse('La propiedad no es una unidad on-plan');
            }

            $delta = (int) $request->delta_units;
            $eventType = ProjectUnitInventoryMovement::MANUAL_ADJUST;

            DB::transaction(function () use ($property, $delta, $eventType, $request) {
                $before = (int) $property->available_units;
                $total = (int) $property->total_units;

                // La constraint chk_units_valid exige available_units <= total_units:
                // al sumar inventario ajustamos también el total para mantenerlo consistente.
                $available = max($before + $delta, 0);
                $total = $delta > 0 ? max($total + $delta, $available) : $total;
                $total = max($total, $available);

                $property->forceFill([
                    'total_units' => $total,
                    'available_units' => $available,
                    'unit_status' => $this->deriveStatus($total, $available),
                ])->save();

                ProjectUnitInventoryMovement::create([
                    'property_id' => $property->id,
                    'project_id' => $property->project_id,
                    'event_type' => $eventType,
                    'delta_units' => $delta,
                    'before_units' => $before,
                    'after_units' => $available,
                    'appointment_id' => $request->appointment_id,
                    'actor_type' => 'user',
                    'actor_id' => Auth::id(),
                    'notes' => $request->notes,
                ]);
            });

            ResponseService::successResponse('Inventario ajustado');
        } catch (Exception $e) {
            ResponseService::errorResponse($e->getMessage());
        }
    }

    /**
     * Historial de movimientos de una propiedad on-plan.
     */
    public function movements($id)
    {
        if (! has_permissions('read', 'project_inventory')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $property = Property::findOrFail($id);
        $movements = ProjectUnitInventoryMovement::where('property_id', $id)
            ->orderByDesc('created_at')
            ->get();

        $eventLabels = [
            'reserve' => ['Reserva', 'info'],
            'confirm' => ['Confirmada', 'success'],
            'cancel' => ['Cancelada', 'warning'],
            'expire' => ['Expirada', 'secondary'],
            'reject' => ['Rechazada', 'danger'],
            'manual_adjust' => ['Ajuste manual', 'primary'],
        ];

        $movements = $movements->map(function ($movement) use ($eventLabels) {
            [$label, $color] = $eventLabels[$movement->event_type] ?? [$movement->event_type, 'info'];

            return [
                'id' => $movement->id,
                'event_type' => $label,
                'event_color' => $color,
                'delta_units' => $movement->delta_units,
                'before_units' => $movement->before_units,
                'after_units' => $movement->after_units,
                'notes' => $movement->notes,
                'created_at' => $movement->created_at ? $movement->created_at->format('d-m-Y H:i') : '-',
            ];
        });

        return view('admin.project-inventory.movements', compact('property', 'movements'));
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
}
