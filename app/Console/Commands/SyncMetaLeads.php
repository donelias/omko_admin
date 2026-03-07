<?php

namespace App\Console\Commands;

use App\Services\MetaService;
use App\Models\User;
use App\Models\AgentMetaCredential;
use Illuminate\Console\Command;

class SyncMetaLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meta:sync-leads {--agent-id= : Sincronizar leads de un agente específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar leads desde Meta con el CRM';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $agentId = $this->option('agent-id');

        if ($agentId) {
            // Sincronizar leads de un agente específico
            $this->sincronizarAgente($agentId);
        } else {
            // Sincronizar leads de todos los agentes con credenciales Meta activas
            $this->sincronizarTodos();
        }
    }

    /**
     * Sincronizar leads de un agente específico
     */
    private function sincronizarAgente($agentId)
    {
        $agent = User::findOrFail($agentId);

        $this->info("Sincronizando leads para agente: {$agent->name}");

        $resultado = MetaService::sincronizarLeadsDesMeta($agentId);

        if ($resultado['success']) {
            $this->info("✅ Sincronización completada:");
            $this->info("   - Leads creados: {$resultado['created']}");
            $this->info("   - Leads actualizados: {$resultado['updated']}");
            $this->info("   - Total procesados: {$resultado['total']}");
        } else {
            $this->error("❌ Error en sincronización: {$resultado['message']}");
        }
    }

    /**
     * Sincronizar leads de todos los agentes
     */
    private function sincronizarTodos()
    {
        $credentials = AgentMetaCredential::where('activa', true)
            ->where(function ($q) {
                $q->whereNull('fecha_expiracion_token')
                  ->orWhere('fecha_expiracion_token', '>', now());
            })
            ->get();

        if ($credentials->isEmpty()) {
            $this->warn("No hay credenciales Meta activas configuradas");
            return;
        }

        $this->info("Sincronizando {$credentials->count()} agentes con credenciales Meta activas...\n");

        $totalCreados = 0;
        $totalActualizados = 0;
        $totalErrores = 0;

        foreach ($credentials as $credential) {
            $agent = $credential->agent;
            $this->info("Sincronizando {$agent->name}...");

            $resultado = MetaService::sincronizarLeadsDesMeta($agent->id);

            if ($resultado['success']) {
                $this->line("  ✅ Creados: {$resultado['created']}, Actualizados: {$resultado['updated']}");
                $totalCreados += $resultado['created'];
                $totalActualizados += $resultado['updated'];
            } else {
                $this->error("  ❌ Error: {$resultado['message']}");
                $totalErrores++;
            }
        }

        $this->newLine();
        $this->info("=== RESUMEN ===");
        $this->info("Leads creados: {$totalCreados}");
        $this->info("Leads actualizados: {$totalActualizados}");
        $this->info("Errores: {$totalErrores}");
    }
}
