<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyAvailability;
use App\Models\ShortTermReservation;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminShortTermController extends Controller
{
    /**
     * Listado de reservas vacacionales (FASE 8, T1) — gestión por el admin.
     */
    public function reservationsIndex()
    {
        if (! has_permissions('read', 'short_term_reservations')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('admin.short-term.reservations');
    }

    /**
     * Tabla server-side de reservas.
     */
    public function getReservationsList(Request $request)
    {
        if (! has_permissions('read', 'short_term_reservations')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $search = $request->input('search');

        $sql = ShortTermReservation::with(['property', 'customer'])
            ->when($request->has('search') && ! empty($search), function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orWhere('status', 'LIKE', "%$search%")
                        ->orWhere('check_in', 'LIKE', "%$search%")
                        ->orWhere('check_out', 'LIKE', "%$search%")
                        ->orWhereHas('property', function ($q) use ($search) {
                            $q->where('title', 'LIKE', "%$search%");
                        })
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%$search%");
                        });
                });
            });

        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = [];
        foreach ($res as $row) {
            $operate = '';
            if (has_permissions('update', 'short_term_reservations')) {
                $operate .= BootstrapTableService::editButton('', true, '#reservationModal', null, $row->id);
            }
            if (has_permissions('delete', 'short_term_reservations')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('admin.short-term.reservations.delete', $row->id));
            }

            $statusClass = [
                'pending' => 'warning',
                'confirmed' => 'success',
                'cancelled' => 'danger',
                'completed' => 'secondary',
            ];

            $rows[] = [
                'id' => $row->id,
                'property_title' => $row->property ? $row->property->title : '-',
                'customer_name' => $row->customer ? $row->customer->name : '-',
                'customer_email' => $row->customer ? $row->customer->email : '-',
                'check_in' => Carbon::parse($row->check_in)->format('d-m-Y'),
                'check_out' => Carbon::parse($row->check_out)->format('d-m-Y'),
                'nights' => $row->nights,
                'guests' => $row->guests,
                'total_price' => $row->total_price !== null ? '$'.number_format($row->total_price, 2) : '-',
                'status' => $row->status,
                'status_badge' => $statusClass[$row->status] ?? 'info',
                'notes' => $row->notes,
                'operate' => $operate,
            ];
        }

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Cambiar estado de una reserva (confirmar / cancelar / completar).
     */
    public function updateReservationStatus(Request $request)
    {
        if (! has_permissions('update', 'short_term_reservations')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:short_term_reservations,id',
            'status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $reservation = ShortTermReservation::findOrFail($request->id);

            if ($reservation->status === 'completed') {
                ResponseService::errorResponse('Esta reserva ya no puede cambiar de estado');
            }

            DB::transaction(function () use ($request, $reservation) {
                // Al cancelar solo cambiamos el estado: los bloqueos manuales de
                // property_availability NO se tocan (la disponibilidad se libera
                // sola porque el chequeo ignora reservas canceladas/completed).
                $reservation->update(['status' => $request->status]);
            });

            ResponseService::successResponse('Estado de la reserva actualizado');
        } catch (Exception $e) {
            ResponseService::errorResponse($e->getMessage());
        }
    }

    public function deleteReservation($id)
    {
        if (! has_permissions('delete', 'short_term_reservations')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            ShortTermReservation::findOrFail($id)->delete();
            ResponseService::successResponse('Reserva eliminada');
        } catch (Exception $e) {
            ResponseService::errorResponse($e->getMessage());
        }
    }

    /**
     * Gestión de disponibilidad / fechas bloqueadas (calendario).
     */
    public function availabilityIndex()
    {
        if (! has_permissions('read', 'short_term_availability')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $properties = Property::where(function ($q) {
            $q->where('is_project_unit', 1);
        })
        ->orWhere(function ($q) {
            $q->where('status', 1)->where('request_status', 'approved')
                ->where('propery_type', 0);
        })
        ->orderBy('title')
        ->get(['id', 'title']);

        return view('admin.short-term.availability', compact('properties'));
    }

    /**
     * Tabla server-side de rangos de disponibilidad.
     */
    public function getAvailabilityList(Request $request)
    {
        if (! has_permissions('read', 'short_term_availability')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $search = $request->input('search');

        $sql = PropertyAvailability::with('property:id,title')
            ->when($request->has('search') && ! empty($search), function ($query) use ($search) {
                $query->where('date_from', 'LIKE', "%$search%")
                    ->orWhere('date_to', 'LIKE', "%$search%")
                    ->orWhereHas('property', function ($q) use ($search) {
                        $q->where('title', 'LIKE', "%$search%");
                    });
            });

        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = [];
        foreach ($res as $row) {
            $operate = '';
            if (has_permissions('delete', 'short_term_availability')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('admin.short-term.availability.delete', $row->id));
            }

            $rows[] = [
                'id' => $row->id,
                'property_title' => $row->property ? $row->property->title : '-',
                'date_from' => Carbon::parse($row->date_from)->format('d-m-Y'),
                'date_to' => Carbon::parse($row->date_to)->format('d-m-Y'),
                'status' => (int) $row->status === 1 ? 'Disponible' : 'Bloqueado',
                'status_badge' => (int) $row->status === 1 ? 'success' : 'danger',
                'nightly_price' => $row->nightly_price !== null ? '$'.number_format($row->nightly_price, 2) : '-',
                'operate' => $operate,
            ];
        }

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Crear rango de disponibilidad o bloqueo.
     */
    public function storeAvailability(Request $request)
    {
        if (! has_permissions('create', 'short_term_availability')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:propertys,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'status' => 'required|in:0,1',
            'nightly_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            PropertyAvailability::create($request->only([
                'property_id', 'date_from', 'date_to', 'status', 'nightly_price',
            ]));

            ResponseService::successResponse('Disponibilidad guardada');
        } catch (Exception $e) {
            ResponseService::errorResponse($e->getMessage());
        }
    }

    public function deleteAvailability($id)
    {
        if (! has_permissions('delete', 'short_term_availability')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            PropertyAvailability::findOrFail($id)->delete();
            ResponseService::successResponse('Rango eliminado');
        } catch (Exception $e) {
            ResponseService::errorResponse($e->getMessage());
        }
    }
}
