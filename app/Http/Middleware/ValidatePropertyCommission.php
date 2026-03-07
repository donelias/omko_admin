<?php

namespace App\Http\Middleware;

use App\Models\PropertyCommission;
use App\Services\CommissionService;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware to validate property transactions have reported commissions
 */
class ValidatePropertyCommission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply to transaction endpoints
        if (!$this->shouldValidate($request)) {
            return $next($request);
        }

        $propertyId = $request->input('property_id');
        $agentId = $request->input('agent_user_id') ?? auth()->id();
        $transactionType = $request->input('transaction_type', 'sale');

        if (!$propertyId || !$agentId) {
            return $next($request);
        }

        // Validate transaction has commission
        $validation = CommissionService::validateTransactionCommission(
            $propertyId,
            $agentId,
            $transactionType
        );

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $validation['error'],
                'code' => $validation['code'],
                'status' => 422,
            ], 422);
        }

        // Store validation result for use in controller
        $request->merge(['commission_validation' => $validation]);

        return $next($request);
    }

    /**
     * Determine if request should be validated
     */
    private function shouldValidate(Request $request): bool
    {
        // Add endpoints that require commission validation
        $validationEndpoints = [
            'transactions',
            'sales',
            'rentals',
            'complete-sale',
            'complete-rental',
        ];

        foreach ($validationEndpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                return true;
            }
        }

        return false;
    }
}
