<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyCommission;
use App\Models\CommissionSetting;
use App\Models\CommissionAlert;
use App\Services\CommissionService;
use App\Rules\PropertyCommissionReported;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PropertyCommissionController extends Controller
{
    /**
     * Display commissions list
     */
    public function index(Request $request)
    {
        if (!has_permissions('read', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = PropertyCommission::with('property', 'agent', 'buyer', 'seller');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_user_id', $request->agent_id);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('property', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            })->orWhereHas('agent', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $commissions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $commissions->items(),
            'pagination' => [
                'total' => $commissions->total(),
                'per_page' => $commissions->perPage(),
                'current_page' => $commissions->currentPage(),
                'last_page' => $commissions->lastPage(),
            ],
        ]);
    }

    /**
     * Create new commission
     */
    public function store(Request $request)
    {
        if (!has_permissions('create', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'property_id' => 'required|exists:propertys,id',
            'agent_user_id' => 'required|exists:users,id',
            'buyer_user_id' => 'nullable|exists:users,id',
            'seller_user_id' => 'nullable|exists:users,id',
            'transaction_type' => 'required|in:sale,rental',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $commission = CommissionService::createCommission(
                $validated['property_id'],
                $validated['agent_user_id'],
                $validated['transaction_type'],
                $validated['buyer_user_id'] ?? null,
                $validated['seller_user_id'] ?? null,
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Commission created successfully',
                'data' => $commission->load('property', 'agent'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Show commission details
     */
    public function show($id)
    {
        if (!has_permissions('read', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $commission = PropertyCommission::with(
            'property',
            'agent',
            'buyer',
            'seller',
            'payment_logs',
            'alerts'
        )->find($id);

        if (!$commission) {
            return response()->json(['message' => 'Commission not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $commission,
        ]);
    }

    /**
     * Approve commission
     */
    public function approve(Request $request, $id)
    {
        if (!has_permissions('update', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $commission = PropertyCommission::find($id);
        if (!$commission) {
            return response()->json(['message' => 'Commission not found'], 404);
        }

        try {
            $commission->approve($request->get('notes'));

            return response()->json([
                'success' => true,
                'message' => 'Commission approved successfully',
                'data' => $commission->load('property', 'agent'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject commission
     */
    public function reject(Request $request, $id)
    {
        if (!has_permissions('update', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $commission = PropertyCommission::find($id);
        if (!$commission) {
            return response()->json(['message' => 'Commission not found'], 404);
        }

        try {
            $commission->reject($validated['rejection_reason']);

            return response()->json([
                'success' => true,
                'message' => 'Commission rejected successfully',
                'data' => $commission,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Record payment
     */
    public function recordPayment(Request $request, $id)
    {
        if (!has_permissions('update', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:bank_transfer,check,cash,paypal,stripe,other',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $commission = PropertyCommission::find($id);
        if (!$commission) {
            return response()->json(['message' => 'Commission not found'], 404);
        }

        try {
            $commission->recordPayment(
                $validated['amount_paid'],
                $validated['payment_method'],
                $validated['payment_reference'] ?? null,
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => $commission->load('payment_logs'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Validate transaction before processing
     */
    public function validateTransaction(Request $request)
    {
        $validated = $request->validate([
            'property_id' => 'required|exists:propertys,id',
            'agent_user_id' => 'required|exists:users,id',
            'transaction_type' => 'required|in:sale,rental',
        ]);

        $validation = CommissionService::validateTransactionCommission(
            $validated['property_id'],
            $validated['agent_user_id'],
            $validated['transaction_type']
        );

        return response()->json($validation);
    }

    /**
     * Get agent dashboard summary
     */
    public function dashboard(Request $request)
    {
        $agentId = $request->get('agent_id', auth()->id());

        if ($agentId != auth()->id() && !has_permissions('read', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $summary = CommissionService::getDashboardSummary($agentId);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get pending commissions for approval
     */
    public function pending(Request $request)
    {
        if (!has_permissions('read', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $commissions = CommissionService::getPendingCommissions($request->get('limit', 10));

        return response()->json([
            'success' => true,
            'data' => $commissions->items(),
            'pagination' => [
                'total' => $commissions->total(),
                'per_page' => $commissions->perPage(),
                'current_page' => $commissions->currentPage(),
                'last_page' => $commissions->lastPage(),
            ],
        ]);
    }

    /**
     * Get alerts for agent
     */
    public function alerts(Request $request)
    {
        $agentId = $request->get('agent_id', auth()->id());

        if ($agentId != auth()->id() && !has_permissions('read', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = CommissionAlert::where('agent_user_id', $agentId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        $alerts = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $alerts->items(),
            'pagination' => [
                'total' => $alerts->total(),
                'per_page' => $alerts->perPage(),
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
            ],
        ]);
    }

    /**
     * Mark alert as read
     */
    public function markAlertRead($alertId)
    {
        $alert = CommissionAlert::find($alertId);
        if (!$alert) {
            return response()->json(['message' => 'Alert not found'], 404);
        }

        if ($alert->agent_user_id != auth()->id() && !has_permissions('update', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $alert->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Alert marked as read',
        ]);
    }

    /**
     * Generate commission report
     */
    public function report(Request $request)
    {
        if (!has_permissions('read', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'agent_id' => 'nullable|exists:users,id',
        ]);

        $report = CommissionService::generateReport(
            $validated['start_date'],
            $validated['end_date'],
            $validated['agent_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Commission settings
     */
    public function settings()
    {
        if (!has_permissions('read', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = CommissionSetting::all();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update commission settings
     */
    public function updateSettings(Request $request)
    {
        if (!has_permissions('update', 'commission')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'commission_type' => 'required|in:percentage,fixed',
            'commission_value' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:10',
            'is_active' => 'boolean',
            'apply_to_rentals' => 'boolean',
            'apply_to_sales' => 'boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        // Deactivate old settings
        CommissionSetting::where('is_active', true)->update(['is_active' => false]);

        // Create new setting
        $setting = CommissionSetting::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Commission settings updated successfully',
            'data' => $setting,
        ], 201);
    }
}
