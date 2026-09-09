<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyAvailability;
use App\Models\ShortTermReservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ShortTermApiController extends Controller
{
    /**
     * Consulta disponibilidad de una propiedad entre fechas.
     * GET /api/short-term/availability?property_id=3&check_in=2026-09-10&check_out=2026-09-15
     */
    public function availability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|integer',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $property = Property::find($request->property_id);

        if (! $property) {
            return response()->json([
                'error' => true,
                'message' => 'Propiedad no encontrada',
            ], 404);
        }

        $checkIn = Carbon::parse($request->check_in);
        $checkOut = Carbon::parse($request->check_out);
        $nights = $checkIn->diffInDays($checkOut);

        // Confirmaciones que se solapan
        $overlappingReservations = ShortTermReservation::where('property_id', $property->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut->copy()->subDay()])
                  ->orWhereBetween('check_out', [$checkIn->copy()->addDay(), $checkOut])
                  ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                      $q2->where('check_in', '<=', $checkIn)->where('check_out', '>=', $checkOut);
                  });
            })
            ->exists();

        // Slots configurados que bloquean la fecha (status=0) para la propiedad
        $blockedSlots = PropertyAvailability::where('property_id', $property->id)
            ->where('status', 0)
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereDate('date_from', '<=', $checkOut->toDateString())
                  ->whereDate('date_to', '>=', $checkIn->toDateString());
            })
            ->exists();

        return response()->json([
            'error' => false,
            'data' => [
                'property_id' => $property->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'nights' => $nights,
                'available' => ! $overlappingReservations && ! $blockedSlots,
                'reasons' => [
                    'existing_reservation' => $overlappingReservations,
                    'blocked_slot' => $blockedSlots,
                ],
            ],
        ]);
    }

    /**
     * Crea una reserva. POST /api/short-term/reservations
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|integer',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'guests' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $property = Property::find($request->property_id);

        if (! $property) {
            return response()->json([
                'error' => true,
                'message' => 'Propiedad no encontrada',
            ], 404);
        }

        $checkIn = Carbon::parse($request->check_in);
        $checkOut = Carbon::parse($request->check_out);
        $nights = $checkIn->diffInDays($checkOut);

        if ($nights < 1) {
            return response()->json([
                'error' => true,
                'message' => 'El check-out debe ser al menos un día después del check-in',
            ], 422);
        }

        // Validar solapamiento con reservas existentes activas
        $conflict = ShortTermReservation::where('property_id', $property->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut->copy()->subDay()])
                  ->orWhereBetween('check_out', [$checkIn->copy()->addDay(), $checkOut])
                  ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                      $q2->where('check_in', '<=', $checkIn)->where('check_out', '>=', $checkOut);
                  });
            })
            ->exists();

        if ($conflict) {
            return response()->json([
                'error' => true,
                'message' => 'La propiedad ya está reservada en ese rango de fechas',
            ], 409);
        }

        $blocked = PropertyAvailability::where('property_id', $property->id)
            ->where('status', 0)
            ->whereDate('date_from', '<=', $checkOut->toDateString())
            ->whereDate('date_to', '>=', $checkIn->toDateString())
            ->exists();

        if ($blocked) {
            return response()->json([
                'error' => true,
                'message' => 'La propiedad no está disponible en ese rango de fechas',
            ], 409);
        }

        // Calcular precio (usar nightly_price configurado o el precio de la propiedad)
        $nightlyPrice = PropertyAvailability::where('property_id', $property->id)
            ->where('status', 1)
            ->whereDate('date_from', '<=', $checkIn->toDateString())
            ->whereDate('date_to', '>=', $checkOut->toDateString())
            ->value('nightly_price');

        $basePrice = $nightlyPrice ?: ($property->price / 30); // tarifa mensual -> diaria aprox
        $totalPrice = round($basePrice * $nights, 2);

        $reservation = ShortTermReservation::create([
            'property_id' => $property->id,
            'customer_id' => Auth::id(),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => $nights,
            'guests' => $request->guests ?? 1,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'error' => false,
            'message' => 'Reserva creada correctamente (pendiente de confirmación)',
            'data' => $reservation,
        ], 201);
    }

    /**
     * Lista reservas del usuario autenticado. GET /api/short-term/my-reservations
     */
    public function myReservations(Request $request)
    {
        $reservations = ShortTermReservation::where('customer_id', Auth::id())
            ->with('property:id,title,city,slug_id,price,currency,title_image')
            ->orderByDesc('check_in')
            ->get();

        return response()->json([
            'error' => false,
            'data' => $reservations,
        ]);
    }

    /**
     * Cancela una reserva propia o de un agente owner. POST /api/short-term/cancel
     */
    public function cancel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $reservation = ShortTermReservation::find($request->id);

        if (! $reservation) {
            return response()->json(['error' => true, 'message' => 'Reserva no encontrada'], 404);
        }

        $isOwner = (int) $reservation->property->added_by === (int) Auth::id();
        if ((int) $reservation->customer_id !== (int) Auth::id() && ! $isOwner) {
            return response()->json(['error' => true, 'message' => 'No autorizado'], 403);
        }

        if (in_array($reservation->status, ['cancelled', 'completed'])) {
            return response()->json(['error' => true, 'message' => 'La reserva ya no puede cancelarse'], 422);
        }

        $reservation->update(['status' => 'cancelled']);

        return response()->json([
            'error' => false,
            'message' => 'Reserva cancelada',
            'data' => $reservation,
        ]);
    }
}
