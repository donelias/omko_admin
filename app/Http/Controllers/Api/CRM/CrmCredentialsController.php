<?php

namespace App\Http\Controllers\Api\CRM;

use App\Models\AgentMetaCredential;
use App\Models\AgentWhatsAppCredential;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CrmCredentialsController extends Controller
{
    // ========== META CREDENTIALS ==========

    /**
     * Listar credenciales Meta del agente
     */
    public function listMetaCredentials()
    {
        $user = Auth::user();

        $credentials = AgentMetaCredential::where('agent_id', $user->id)
            ->latest('created_at')
            ->paginate(10);

        return response()->json([
            'data' => $credentials->items(),
            'pagination' => [
                'total' => $credentials->total(),
                'per_page' => $credentials->perPage(),
            ]
        ]);
    }

    /**
     * Crear nueva credencial Meta
     */
    public function storeMetaCredential(Request $request)
    {
        $validated = $request->validate([
            'meta_app_id' => 'required|string',
            'meta_app_secret' => 'required|string',
            'meta_access_token' => 'required|string',
            'meta_business_account_id' => 'required|string',
            'meta_ad_account_id' => 'required|string',
            'nombre_cuenta' => 'required|string|max:255',
            'email_cuenta' => 'required|email',
            'fecha_expiracion_token' => 'nullable|date',
        ]);

        // Generar webhook token único
        $validated['webhook_verify_token'] = Str::random(32);
        $validated['agent_id'] = Auth::id();
        $validated['activa'] = true;
        $validated['fecha_conexion'] = now();

        $credential = AgentMetaCredential::create($validated);

        return response()->json([
            'message' => 'Credencial Meta creada exitosamente',
            'data' => $credential,
            'webhook_token' => $credential->webhook_verify_token
        ], 201);
    }

    /**
     * Obtener detalles de credencial Meta
     */
    public function showMetaCredential($id)
    {
        $credential = AgentMetaCredential::findOrFail($id);

        // Autorización
        if (Auth::user()->id !== $credential->agent_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json([
            'data' => $credential,
            'token_activo' => $credential->tokenActivo(),
            'dias_expiracion' => $credential->diasHastaExpiracion(),
        ]);
    }

    /**
     * Actualizar credencial Meta
     */
    public function updateMetaCredential(Request $request, $id)
    {
        $credential = AgentMetaCredential::findOrFail($id);

        // Autorización
        if (Auth::user()->id !== $credential->agent_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre_cuenta' => 'sometimes|string|max:255',
            'email_cuenta' => 'sometimes|email',
            'fecha_expiracion_token' => 'nullable|date',
            'activa' => 'sometimes|boolean',
        ]);

        $credential->update($validated);

        return response()->json([
            'message' => 'Credencial Meta actualizada exitosamente',
            'data' => $credential
        ]);
    }

    /**
     * Renovar token Meta
     */
    public function renovarTokenMeta(Request $request, $id)
    {
        $credential = AgentMetaCredential::findOrFail($id);

        // Autorización
        if (Auth::user()->id !== $credential->agent_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'meta_access_token' => 'required|string',
            'fecha_expiracion_token' => 'nullable|date',
        ]);

        $credential->update($validated);

        return response()->json([
            'message' => 'Token Meta renovado exitosamente',
            'data' => $credential
        ]);
    }

    /**
     * Desactivar credencial Meta
     */
    public function desactivarMetaCredential($id)
    {
        $credential = AgentMetaCredential::findOrFail($id);

        // Autorización
        if (Auth::user()->id !== $credential->agent_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $credential->desactivar();

        return response()->json([
            'message' => 'Credencial Meta desactivada'
        ]);
    }

    /**
     * Eliminar credencial Meta
     */
    public function destroyMetaCredential($id)
    {
        $credential = AgentMetaCredential::findOrFail($id);

        // Autorización
        if (Auth::user()->id !== $credential->agent_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $credential->delete();

        return response()->json([
            'message' => 'Credencial Meta eliminada'
        ]);
    }

    // ========== WHATSAPP CREDENTIALS ==========

    /**
     * Listar credenciales WhatsApp del agente
     */
    public function listWhatsAppCredentials()
    {
        $user = Auth::user();

        $credentials = AgentWhatsAppCredential::where('agent_id', $user->id)
            ->latest('created_at')
            ->paginate(10);

        return response()->json([
            'data' => $credentials->items(),
            'pagination' => [
                'total' => $credentials->total(),
                'per_page' => $credentials->perPage(),
            ]
        ]);
    }

    /**
     * Crear nueva credencial WhatsApp
     */
    public function storeWhatsAppCredential(Request $request)
    {
        $validated = $request->validate([
            'phone_number_id' => 'required|string',
            'business_account_id' => 'required|string',
            'access_token' => 'required|string',
            'phone_number' => 'required|string|max:20',
            'nombre_cuenta' => 'required|string|max:255',
            'numeros_permitidos' => 'nullable|array',
            'fecha_expiracion_token' => 'nullable|date',
        ]);

        // Generar webhook token único
        $validated['webhook_verify_token'] = Str::random(32);
        $validated['agent_id'] = Auth::id();
        $validated['activa'] = true;
        $validated['fecha_conexion'] = now();

        $credential = AgentWhatsAppCredential::create($validated);

        return response()->json([
            'message' => 'Credencial WhatsApp creada exitosamente',
            'data' => $credential,
            'webhook_token' => $credential->webhook_verify_token
        ], 201);
    }

    /**
     * Obtener detalles de credencial WhatsApp
     */
    public function showWhatsAppCredential($id)
    {
        $credential = AgentWhatsAppCredential::findOrFail($id);

        // Autorización
        if (Auth::user()->id !== $credential->agent_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json([
            'data' => $credential,
            'token_activo' => $credential->tokenActivo(),
            'dias_expiracion' => $credential->diasHastaExpiracion(),
            'numero_formateado' => $credential->obtenerNumerFormateado(),
        ]);
    }

    /**
     * Actualizar credencial WhatsApp
     */
    public function updateWhatsAppCredential(Request $request, $id)
    {
        $credential = AgentWhatsAppCredential::findOrFail($id);

        // Autorización
        if (Auth::user()->id !== $credential->agent_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre_cuenta' => 'sometimes|string|max:255',
            'numeros_permitidos' => 'nullable|array',
            'fecha_expiracion_token' => 'nullable|date',
            'activa' => 'sometimes|boolean',
        ]);

        $credential->update($validated);

        return response()->json([
            'message' => 'Credencial WhatsApp actualizada exitosamente',
            'data' => $credential
        ]);
    }

    /**
     * Renovar token WhatsApp
     */
    public function renovarTokenWhatsApp(Request $request, $id)
    {
        $credential = AgentWhatsAppCredential::findOrFail($id);

        // Autorización
        if (Auth::user()->id !== $credential->agent_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'access_token' => 'required|string',
            'fecha_expiracion_token' => 'nullable|date',
        ]);

        $credential->update($validated);

        return response()->json([
            'message' => 'Token WhatsApp renovado exitosamente',
            'data' => $credential
        ]);
    }

    /**
     * Desactivar credencial WhatsApp
     */
    public function desactivarWhatsAppCredential($id)
    {
        $credential = AgentWhatsAppCredential::findOrFail($id);

        // Autorización
        if (Auth::user()->id !== $credential->agent_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $credential->desactivar();

        return response()->json([
            'message' => 'Credencial WhatsApp desactivada'
        ]);
    }

    /**
     * Eliminar credencial WhatsApp
     */
    public function destroyWhatsAppCredential($id)
    {
        $credential = AgentWhatsAppCredential::findOrFail($id);

        // Autorización
        if (Auth::user()->id !== $credential->agent_id && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $credential->delete();

        return response()->json([
            'message' => 'Credencial WhatsApp eliminada'
        ]);
    }

    // ========== ADMIN ENDPOINTS ==========

    /**
     * Obtener todas las credenciales (solo admin)
     */
    public function todasLasCredenciales()
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $metaCredentials = AgentMetaCredential::with('agent')->get();
        $whatsappCredentials = AgentWhatsAppCredential::with('agent')->get();

        return response()->json([
            'meta_credentials' => $metaCredentials,
            'whatsapp_credentials' => $whatsappCredentials,
            'total' => count($metaCredentials) + count($whatsappCredentials)
        ]);
    }

    /**
     * Obtener tokens próximos a expirar (solo admin)
     */
    public function proximosAExpirar()
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $metaExpirando = AgentMetaCredential::proximasAExpirar()->with('agent')->get();
        $whatsappExpirando = AgentWhatsAppCredential::proximasAExpirar()->with('agent')->get();

        return response()->json([
            'meta_expirando' => $metaExpirando,
            'whatsapp_expirando' => $whatsappExpirando,
            'total_expirando' => count($metaExpirando) + count($whatsappExpirando)
        ]);
    }
}
