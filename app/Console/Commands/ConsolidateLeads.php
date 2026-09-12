<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Contactrequests;
use App\Models\Customer;
use App\Models\InterestedUser;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertysInquiry;
use Illuminate\Console\Command;

class ConsolidateLeads extends Command
{
    protected $signature = 'leads:consolidate {--source=all : Fuente a consolidar (interested|inquiry|contact|appointment|all)}';

    protected $description = 'Consolida en crm_leads las fuentes legacy de interés/contacto (interested_users, propertys_inquiry, contactrequests, appointments). Idempotente vía metadata.imported_from.';

    public function handle(): int
    {
        $source = $this->option('source');

        $imported = 0;
        $skipped = 0;

        if (in_array($source, ['interested', 'all'], true)) {
            [$i, $s] = $this->importInterested();
            $imported += $i;
            $skipped += $s;
        }

        if (in_array($source, ['inquiry', 'all'], true)) {
            [$i, $s] = $this->importInquiry();
            $imported += $i;
            $skipped += $s;
        }

        if (in_array($source, ['contact', 'all'], true)) {
            [$i, $s] = $this->importContact();
            $imported += $i;
            $skipped += $s;
        }

        if (in_array($source, ['appointment', 'all'], true)) {
            [$i, $s] = $this->importAppointments();
            $imported += $i;
            $skipped += $s;
        }

        $this->info("Leads importados: {$imported}. Omitidos (ya existentes): {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * Agente responsable por defecto cuando no hay propiedad de la que derivar el dueño:
     * el agente (customers.is_agent=1) con menos leads asignados (distribución mínima).
     */
    protected function defaultAgentId(): int
    {
        $default = Customer::where('is_agent', 1)
            ->withCount('leads')
            ->orderBy('leads_count', 'asc')
            ->value('id');

        if ($default) {
            return (int) $default;
        }

        $admin = Customer::orderBy('id')->value('id');

        return $admin ? (int) $admin : 1;
    }

    protected function agentForProperty(?int $propertyId): int
    {
        if ($propertyId) {
            $addedBy = Property::where('id', $propertyId)->value('added_by');
            // added_by puede ser un customers.id (agente) o 0 (propiedad admin).
            if (! empty($addedBy)) {
                return (int) $addedBy;
            }
        }

        return $this->defaultAgentId();
    }

    protected function importInterested(): array
    {
        $imported = 0;
        $skipped = 0;

        $rows = InterestedUser::with('customer')->get();

        foreach ($rows as $row) {
            if ($this->alreadyImported('interested', $row->id)) {
                $skipped++;
                continue;
            }

            $customer = $row->customer;
            $metadata = ['imported_from' => "interested:{$row->id}"];

            Lead::create([
                'nombre' => $customer?->name ?? 'Interesado',
                'email' => $customer?->email,
                'telefono' => $customer?->full_mobile ?? $customer?->mobile,
                'property_id' => $row->property_id,
                'agent_id' => $this->agentForProperty($row->property_id),
                'origin' => 'formulario',
                'notas' => 'Importado de interested_users al consolidar fuentes legacy.',
                'metadata' => $metadata,
            ]);

            $imported++;
        }

        return [$imported, $skipped];
    }

    protected function importInquiry(): array
    {
        $imported = 0;
        $skipped = 0;

        $rows = PropertysInquiry::with('customer')->get();

        foreach ($rows as $row) {
            if ($this->alreadyImported('inquiry', $row->id)) {
                $skipped++;
                continue;
            }

            $customer = $row->customer;
            $metadata = ['imported_from' => "inquiry:{$row->id}"];

            Lead::create([
                'nombre' => $customer?->name ?? 'Interesado',
                'email' => $customer?->email,
                'telefono' => $customer?->full_mobile ?? $customer?->mobile,
                'property_id' => $row->propertys_id,
                'agent_id' => $this->agentForProperty($row->propertys_id),
                'origin' => 'formulario',
                'notas' => 'Importado de propertys_inquiry al consolidar fuentes legacy.',
                'metadata' => $metadata,
            ]);

            $imported++;
        }

        return [$imported, $skipped];
    }

    protected function importContact(): array
    {
        $imported = 0;
        $skipped = 0;

        $rows = Contactrequests::all();

        foreach ($rows as $row) {
            if ($this->alreadyImported('contact', $row->id)) {
                $skipped++;
                continue;
            }

            $metadata = ['imported_from' => "contact:{$row->id}"];

            Lead::create([
                'nombre' => trim($row->first_name.' '.$row->last_name) ?: 'Contacto',
                'email' => $row->email,
                'telefono' => $row->telefono,
                'agent_id' => $this->defaultAgentId(),
                'origin' => 'formulario',
                'notas' => "Asunto: {$row->subject}".($row->message ? "\nMensaje: {$row->message}" : ''),
                'metadata' => $metadata,
            ]);

            $imported++;
        }

        return [$imported, $skipped];
    }

    protected function importAppointments(): array
    {
        $imported = 0;
        $skipped = 0;

        $rows = Appointment::with(['user', 'property'])->get();

        foreach ($rows as $row) {
            if ($this->alreadyImported('appointment', $row->id)) {
                $skipped++;
                continue;
            }

            $customer = $row->user;
            $metadata = ['imported_from' => "appointment:{$row->id}"];

            Lead::create([
                'nombre' => $customer?->name ?? 'Cita',
                'email' => $customer?->email,
                'telefono' => $customer?->full_mobile ?? $customer?->mobile,
                'property_id' => $row->property_id,
                'agent_id' => $row->agent_id ?: $this->agentForProperty($row->property_id),
                'origin' => 'formulario',
                'notas' => "Importado de cita (appointment). Tipo: {$row->meeting_type}.",
                'metadata' => $metadata,
            ]);

            $imported++;
        }

        return [$imported, $skipped];
    }

    protected function alreadyImported(string $sourcePrefix, int $sourceId): bool
    {
        $mark = "{$sourcePrefix}:{$sourceId}";

        return Lead::where('metadata->imported_from', $mark)->exists();
    }
}
