<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ProjectUnitInventoryMovement;
use App\Models\Property;

class ProjectUnitInventoryService
{
    /**
     * Reserve 1 unit for project-unit properties.
     */
    public function reserveForAppointment(Property $property, ?int $appointmentId, string $actorType, ?int $actorId = null, ?string $notes = null): array
    {
        if (! $this->isTrackableProjectUnit($property)) {
            return ['success' => true, 'message' => null];
        }

        $lockedProperty = Property::where('id', $property->id)->lockForUpdate()->first();
        if (! $lockedProperty || ! $this->isTrackableProjectUnit($lockedProperty)) {
            return ['success' => true, 'message' => null];
        }

        if ($lockedProperty->unit_status === 'inactive') {
            return ['success' => false, 'message' => trans('This unit is inactive and cannot be reserved.')];
        }

        $beforeUnits = (int) ($lockedProperty->available_units ?? 0);
        if ($beforeUnits <= 0) {
            return ['success' => false, 'message' => trans('This unit is sold out.')];
        }

        $afterUnits = $beforeUnits - 1;
        $lockedProperty->available_units = $afterUnits;
        $lockedProperty->unit_status = $this->resolveStatusByAvailableUnits($afterUnits, $lockedProperty->unit_status);
        $lockedProperty->save();

        ProjectUnitInventoryMovement::create([
            'property_id' => $lockedProperty->id,
            'project_id' => $lockedProperty->project_id,
            'event_type' => 'reserve',
            'delta_units' => -1,
            'before_units' => $beforeUnits,
            'after_units' => $afterUnits,
            'appointment_id' => $appointmentId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'notes' => $notes,
        ]);

        return ['success' => true, 'message' => null];
    }

    /**
     * Release 1 unit for project-unit properties when appointment is cancelled/rejected/expired.
     */
    public function releaseForAppointment(Appointment $appointment, string $eventType, string $actorType, ?int $actorId = null, ?string $notes = null): array
    {
        $property = Property::where('id', $appointment->property_id)->first();
        if (! $property || ! $this->isTrackableProjectUnit($property)) {
            return ['success' => true, 'message' => null];
        }

        $lockedProperty = Property::where('id', $property->id)->lockForUpdate()->first();
        if (! $lockedProperty || ! $this->isTrackableProjectUnit($lockedProperty)) {
            return ['success' => true, 'message' => null];
        }

        if ($lockedProperty->unit_status === 'inactive') {
            return ['success' => true, 'message' => null];
        }

        $beforeUnits = (int) ($lockedProperty->available_units ?? 0);
        $totalUnits = $lockedProperty->total_units !== null ? (int) $lockedProperty->total_units : null;

        $afterUnits = $beforeUnits + 1;
        if ($totalUnits !== null && $afterUnits > $totalUnits) {
            $afterUnits = $totalUnits;
        }

        if ($afterUnits === $beforeUnits) {
            return ['success' => true, 'message' => null];
        }

        $lockedProperty->available_units = $afterUnits;
        $lockedProperty->unit_status = $this->resolveStatusByAvailableUnits($afterUnits, $lockedProperty->unit_status);
        $lockedProperty->save();

        ProjectUnitInventoryMovement::create([
            'property_id' => $lockedProperty->id,
            'project_id' => $lockedProperty->project_id,
            'event_type' => $eventType,
            'delta_units' => +1,
            'before_units' => $beforeUnits,
            'after_units' => $afterUnits,
            'appointment_id' => $appointment->id,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'notes' => $notes,
        ]);

        return ['success' => true, 'message' => null];
    }

    private function isTrackableProjectUnit(Property $property): bool
    {
        return (bool) $property->is_project_unit && ! empty($property->project_id);
    }

    private function resolveStatusByAvailableUnits(int $availableUnits, ?string $currentStatus = null): string
    {
        if ($currentStatus === 'inactive') {
            return 'inactive';
        }

        if ($availableUnits <= 0) {
            return 'sold_out';
        }

        if ($availableUnits <= 3) {
            return 'low_stock';
        }

        return 'available';
    }
}
