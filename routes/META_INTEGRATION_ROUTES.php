<?php

use Illuminate\Support\Facades\Route;

// Meta Webhook Routes - Sin autenticación (necesita validación especial)
Route::prefix('webhooks/meta')->group(function () {
    // Validación de webhook
    Route::get('/', 'Api\CRM\MetaWebhookController@verify')->name('meta.webhook.verify');
    
    // Recibir eventos
    Route::post('/', 'Api\CRM\MetaWebhookController@handle')->name('meta.webhook.handle');
});

// Meta Integration Routes - Con autenticación
Route::middleware('auth:sanctum')->prefix('crm/meta')->group(function () {
    // Logs
    Route::get('/logs', 'Api\CRM\MetaWebhookController@obtenerLogs');
    
    // Sincronización manual
    Route::post('/sync-leads', 'Api\CRM\MetaWebhookController@sincronizarLeads');
    
    // Campañas
    Route::post('/campaigns', 'Api\CRM\MetaWebhookController@crearCampaña');
    Route::get('/campaigns/{campaignId}/leads', 'Api\CRM\MetaWebhookController@obtenerLeadsDeCampaña');
    
    // Mensajes
    Route::post('/messages', 'Api\CRM\MetaWebhookController@enviarMensaje');
    
    // Tokens
    Route::post('/tokens/renew', 'Api\CRM\MetaWebhookController@renovarToken');
});
