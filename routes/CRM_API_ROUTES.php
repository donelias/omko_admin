<?php

use Illuminate\Support\Facades\Route;

// CRM Routes - Incluir en routes/api.php

Route::middleware('auth:sanctum')->prefix('crm')->group(function () {
    // ========== LEADS ==========
    Route::prefix('leads')->group(function () {
        Route::get('/', 'Api\CRM\CrmLeadController@index');              // Listar leads
        Route::post('/', 'Api\CRM\CrmLeadController@store');              // Crear lead
        Route::get('/{id}', 'Api\CRM\CrmLeadController@show');            // Detalles lead
        Route::put('/{id}', 'Api\CRM\CrmLeadController@update');          // Actualizar lead
        Route::post('/{id}/status', 'Api\CRM\CrmLeadController@cambiarStatus'); // Cambiar status
        Route::get('/calientes', 'Api\CRM\CrmLeadController@leadsCalientes'); // Leads calientes
        Route::get('/dashboard', 'Api\CRM\CrmLeadController@dashboard');  // Dashboard stats
        Route::delete('/{id}', 'Api\CRM\CrmLeadController@destroy');      // Eliminar lead

        // Admin endpoints
        Route::get('/agent/{agentId}', 'Api\CRM\CrmLeadController@leadsDelAgente'); // Leads por agente (admin)
    });

    // ========== INTERACTIONS ==========
    Route::prefix('leads/{leadId}/interactions')->group(function () {
        Route::get('/', 'Api\CRM\CrmInteractionController@index');                   // Listar interacciones
        Route::post('/', 'Api\CRM\CrmInteractionController@store');                  // Crear interacción
        Route::get('/{interactionId}', 'Api\CRM\CrmInteractionController@show');     // Detalles interacción
        Route::put('/{interactionId}', 'Api\CRM\CrmInteractionController@update');   // Actualizar interacción
        Route::delete('/{interactionId}', 'Api\CRM\CrmInteractionController@destroy'); // Eliminar interacción
        Route::post('/{interactionId}/completar', 'Api\CRM\CrmInteractionController@marcarCompletada'); // Marcar completada
        Route::get('/{interactionId}/resumen', 'Api\CRM\CrmInteractionController@resumenPorTipo');     // Resumen por tipo
    });

    // General interaction endpoints
    Route::prefix('interactions')->group(function () {
        Route::get('/', 'Api\CRM\CrmInteractionController@interaccionesDelAgente');     // Todas del agente
        Route::get('/pendientes', 'Api\CRM\CrmInteractionController@proximasAcciones'); // Próximas acciones
    });

    // ========== CAMPAIGNS ==========
    Route::prefix('campaigns')->group(function () {
        Route::get('/', 'Api\CRM\CrmCampaignController@index');                    // Listar campañas
        Route::post('/', 'Api\CRM\CrmCampaignController@store');                   // Crear campaña
        Route::get('/{id}', 'Api\CRM\CrmCampaignController@show');                 // Detalles campaña
        Route::put('/{id}', 'Api\CRM\CrmCampaignController@update');               // Actualizar campaña
        Route::post('/{id}/status', 'Api\CRM\CrmCampaignController@cambiarStatus');     // Cambiar status
        Route::post('/{id}/metricas', 'Api\CRM\CrmCampaignController@actualizarMetricas'); // Actualizar métricas
        Route::post('/{id}/leads', 'Api\CRM\CrmCampaignController@agregarLeads');       // Agregar leads
        Route::get('/{id}/leads', 'Api\CRM\CrmCampaignController@leadsDelaCampaña');    // Listar leads de campaña
        Route::get('/{id}/convertidos', 'Api\CRM\CrmCampaignController@leadsConvertidos'); // Leads convertidos
        Route::get('/dashboard', 'Api\CRM\CrmCampaignController@dashboard');       // Dashboard
        Route::delete('/{id}', 'Api\CRM\CrmCampaignController@destroy');           // Eliminar campaña
    });

    // ========== CREDENTIALS ==========
    // Meta Credentials
    Route::prefix('credentials/meta')->group(function () {
        Route::get('/', 'Api\CRM\CrmCredentialsController@listMetaCredentials');        // Listar
        Route::post('/', 'Api\CRM\CrmCredentialsController@storeMetaCredential');       // Crear
        Route::get('/{id}', 'Api\CRM\CrmCredentialsController@showMetaCredential');     // Detalles
        Route::put('/{id}', 'Api\CRM\CrmCredentialsController@updateMetaCredential');   // Actualizar
        Route::post('/{id}/renovar', 'Api\CRM\CrmCredentialsController@renovarTokenMeta'); // Renovar token
        Route::post('/{id}/desactivar', 'Api\CRM\CrmCredentialsController@desactivarMetaCredential'); // Desactivar
        Route::delete('/{id}', 'Api\CRM\CrmCredentialsController@destroyMetaCredential'); // Eliminar
    });

    // WhatsApp Credentials
    Route::prefix('credentials/whatsapp')->group(function () {
        Route::get('/', 'Api\CRM\CrmCredentialsController@listWhatsAppCredentials');        // Listar
        Route::post('/', 'Api\CRM\CrmCredentialsController@storeWhatsAppCredential');       // Crear
        Route::get('/{id}', 'Api\CRM\CrmCredentialsController@showWhatsAppCredential');     // Detalles
        Route::put('/{id}', 'Api\CRM\CrmCredentialsController@updateWhatsAppCredential');   // Actualizar
        Route::post('/{id}/renovar', 'Api\CRM\CrmCredentialsController@renovarTokenWhatsApp'); // Renovar token
        Route::post('/{id}/desactivar', 'Api\CRM\CrmCredentialsController@desactivarWhatsAppCredential'); // Desactivar
        Route::delete('/{id}', 'Api\CRM\CrmCredentialsController@destroyWhatsAppCredential'); // Eliminar
    });

    // Admin Credentials Endpoints
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/credentials', 'Api\CRM\CrmCredentialsController@todasLasCredenciales');
        Route::get('/credentials/expirando', 'Api\CRM\CrmCredentialsController@proximosAExpirar');
    });
});
