<?php

use App\Http\Controllers\PriceIntelligenceController;
use Illuminate\Support\Facades\Route;

/**
 * PRICE INTELLIGENCE ROUTES
 * 
 * Agregar estas rutas a routes/api.php dentro del grupo autenticado
 */

// Sugerencias de precios
Route::prefix('price-intelligence')->group(function () {
    // Get price suggestion for a property
    Route::get('suggestions/{propertyId}', [PriceIntelligenceController::class, 'getSuggestion']);

    // Get detailed price analysis
    Route::get('analysis/{propertyId}', [PriceIntelligenceController::class, 'getAnalysis']);

    // Get market analysis for a location
    Route::post('market-analysis', [PriceIntelligenceController::class, 'getMarketAnalysis']);

    // Record price history
    Route::post('price-history', [PriceIntelligenceController::class, 'recordPriceHistory']);

    // Get bulk suggestions
    Route::post('bulk-suggestions', [PriceIntelligenceController::class, 'getBulkSuggestions']);

    // Get comparable properties
    Route::get('comparables/{propertyId}', [PriceIntelligenceController::class, 'getComparables']);

    // Get price trends
    Route::get('trends', [PriceIntelligenceController::class, 'getTrends']);
});

/**
 * En el archivo routes/api.php, agregar en la sección de imports:
 * 
 * use App\Http\Controllers\PriceIntelligenceController;
 */
