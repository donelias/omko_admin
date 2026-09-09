<?php

namespace App\Services;

use App\Models\Projects;
use App\Models\Property;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProjectUnitSyncService
{
    /**
     * Create or update project unit properties from the provided plans payload.
     */
    public function syncProjectUnitsFromPlans(Projects $project, array $plans): void
    {
        foreach ($plans as $plan) {
            try {
                $unitCode = $this->resolveUnitCode($plan);
                if (! $unitCode) {
                    continue;
                }

                $property = Property::where('project_id', $project->id)
                    ->where('unit_code', $unitCode)
                    ->first();

                // Fallback: if unit_code lookup failed and plan has an id, try PLAN_{id}
                if (! $property && ! empty($plan['id'])) {
                    $property = Property::where('project_id', $project->id)
                        ->where('unit_code', 'PLAN_'.$plan['id'])
                        ->first();
                }

                if (! $property) {
                    $property = new Property;
                    $property->slug_id = generateUniqueSlug($plan['title'] ?? $project->title ?? 'project-unit', 1);
                    $property->title_image = '';
                    $property->three_d_image = '';
                }

                $property->project_id = $project->id;
                $property->is_project_unit = true;
                $property->unit_code = $unitCode;

                $property->title = $plan['title'] ?? $property->title ?? ($project->title.' - '.$unitCode);
                $property->description = $project->description ?? $property->description ?? '';

                $projectLocation = $project->location ?? '';
                $property->address = $plan['location'] ?? $property->address ?? $projectLocation;
                $property->client_address = $plan['location'] ?? $property->client_address ?? $projectLocation;

                $property->propery_type = 0;

                $property->category_id = $plan['category_id'] ?? $property->category_id ?? (string) ($project->category_id ?? '0');

                $price = $this->toNullableInt($plan['price'] ?? null);
                if ($price !== null) {
                    $property->price = $price;
                } elseif (! $property->exists) {
                    $property->price = 0;
                }

                $currency = $plan['currency'] ?? $property->currency ?? 'USD';
                $property->currency = $this->normalizeCurrency($currency);

                $property->country = $plan['country'] ?? $property->country ?? $project->country;
                $property->state = $plan['state'] ?? $property->state ?? $project->state;
                $property->city = $plan['city'] ?? $property->city ?? $project->city;
                $property->latitude = (string) ($plan['latitude'] ?? $property->latitude ?? $project->latitude ?? '');
                $property->longitude = (string) ($plan['longitude'] ?? $property->longitude ?? $project->longitude ?? '');

                $property->added_by = $project->added_by ?? $property->added_by ?? 0;
                $property->role_context = $project->role_context ?? $property->role_context ?? 'user';
                $property->is_premium = $project->is_premium ?? $property->is_premium ?? 0;
                $property->expiry_date = $project->expiry_date ?? $property->expiry_date;

                $totalUnits = array_key_exists('total_units', $plan) ? $this->toNullableInt($plan['total_units']) : $property->total_units;
                $availableUnits = array_key_exists('available_units', $plan) ? $this->toNullableInt($plan['available_units']) : $property->available_units;

                if ($availableUnits === null && $totalUnits !== null && ! $property->exists) {
                    $availableUnits = $totalUnits;
                }

                if ($totalUnits !== null && $availableUnits !== null && $availableUnits > $totalUnits) {
                    $availableUnits = $totalUnits;
                }

                $property->total_units = $totalUnits;
                $property->available_units = $availableUnits;

                $soldUnits = array_key_exists('sold_units', $plan) ? $this->toNullableInt($plan['sold_units']) : $property->sold_units;
                $reservedUnits = array_key_exists('reserved_units', $plan) ? $this->toNullableInt($plan['reserved_units']) : $property->reserved_units;

                $property->sold_units = $soldUnits;
                $property->reserved_units = $reservedUnits;

                $requestedStatus = $plan['unit_status'] ?? null;
                $property->unit_status = $this->resolveUnitStatus($requestedStatus, $availableUnits);

                $property->request_status = $project->request_status ?? $property->request_status ?? 'pending';
                $property->status = $property->unit_status === 'inactive' ? 0 : ($project->status ?? $property->status ?? 0);

                $property->save();
            } catch (\Exception $e) {
                Log::error('Plan unit sync skipped for plan: '.($plan['title'] ?? 'unknown'), [
                    'error' => $e->getMessage(),
                    'plan' => $plan,
                ]);
            }
        }
    }

    /**
     * Deactivate units associated with removed plan IDs.
     */
    public function deactivateUnitsByPlanIds(Projects $project, array $planIds): void
    {
        $unitCodes = collect($planIds)
            ->filter()
            ->map(fn ($id) => 'PLAN_'.$id)
            ->values()
            ->all();

        if (empty($unitCodes)) {
            return;
        }

        Property::where('project_id', $project->id)
            ->where('is_project_unit', true)
            ->whereIn('unit_code', $unitCodes)
            ->update([
                'unit_status' => 'inactive',
                'status' => 0,
            ]);
    }

    /**
     * Deactivate all project units when a project is deleted.
     */
    public function deactivateAllProjectUnits(Projects $project): void
    {
        Property::where('project_id', $project->id)
            ->where('is_project_unit', true)
            ->update([
                'unit_status' => 'inactive',
                'status' => 0,
            ]);
    }

    private function resolveUnitCode(array $plan): ?string
    {
        if (! empty($plan['unit_code'])) {
            $normalized = Str::upper(Str::slug((string) $plan['unit_code'], '_'));

            return $normalized ?: null;
        }

        if (! empty($plan['id'])) {
            return 'PLAN_'.(string) $plan['id'];
        }

        if (! empty($plan['title'])) {
            $normalized = Str::upper(Str::slug((string) $plan['title'], '_'));

            return $normalized ?: null;
        }

        return null;
    }

    private function resolveUnitStatus($requestedStatus, ?int $availableUnits): ?string
    {
        $allowedStatuses = ['available', 'low_stock', 'sold_out', 'inactive'];

        if (in_array($requestedStatus, $allowedStatuses, true)) {
            return $requestedStatus;
        }

        if ($availableUnits === null) {
            return null;
        }

        if ($availableUnits <= 0) {
            return 'sold_out';
        }

        if ($availableUnits <= 3) {
            return 'low_stock';
        }

        return 'available';
    }

    private function toNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return null;
    }

    private function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (strlen($currency) !== 3) {
            return 'USD';
        }

        return $currency;
    }
}
