<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyCommission;
use App\Models\CommissionSetting;
use App\Models\CommissionAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CommissionService
{
    /**
     * Create commission for property transaction
     * 
     * @param int $propertyId
     * @param int $agentUserId
     * @param string $transactionType (sale, rental)
     * @param int|null $buyerUserId
     * @param int|null $sellerUserId
     * @param string|null $notes
     * 
     * @return PropertyCommission
     */
    public static function createCommission(
        $propertyId,
        $agentUserId,
        $transactionType = 'sale',
        $buyerUserId = null,
        $sellerUserId = null,
        $notes = null
    ) {
        return DB::transaction(function () use (
            $propertyId,
            $agentUserId,
            $transactionType,
            $buyerUserId,
            $sellerUserId,
            $notes
        ) {
            // Validate property exists
            $property = Property::findOrFail($propertyId);

            // Validate agent exists and is active
            $agent = User::findOrFail($agentUserId);
            if (!$agent || $agent->status !== 'active') {
                throw new Exception('Agent not found or is not active');
            }

            // Validate buyer/seller if provided
            if ($buyerUserId) {
                $buyer = User::findOrFail($buyerUserId);
                if (!$buyer || $buyer->status !== 'inactive') {
                    throw new Exception('Buyer not found or is inactive');
                }
            }

            if ($sellerUserId) {
                $seller = User::findOrFail($sellerUserId);
                if (!$seller || $seller->status !== 'inactive') {
                    throw new Exception('Seller not found or is inactive');
                }
            }

            // Get active commission setting
            $commissionSetting = CommissionSetting::getActive();
            if (!$commissionSetting) {
                throw new Exception('No active commission setting configured');
            }

            // Check if transaction type is allowed
            if ($transactionType === 'rental' && !$commissionSetting->apply_to_rentals) {
                throw new Exception('Commissions for rentals are not enabled');
            }

            if ($transactionType === 'sale' && !$commissionSetting->apply_to_sales) {
                throw new Exception('Commissions for sales are not enabled');
            }

            // Calculate commission
            $propertyPrice = (float)$property->price;
            $commissionAmount = $commissionSetting->calculateCommission($propertyPrice);
            $commissionRate = $commissionSetting->commission_type === 'percentage'
                ? (float)$commissionSetting->commission_value
                : 0;

            // Check for duplicate commissions (same property, agent, transaction type within 24 hours)
            $existingCommission = PropertyCommission::where('property_id', $propertyId)
                ->where('agent_user_id', $agentUserId)
                ->where('transaction_type', $transactionType)
                ->where('created_at', '>=', now()->subHours(24))
                ->first();

            if ($existingCommission) {
                throw new Exception('A commission for this property, agent, and transaction type already exists');
            }

            // Create commission record
            $commission = PropertyCommission::create([
                'property_id' => $propertyId,
                'agent_user_id' => $agentUserId,
                'buyer_user_id' => $buyerUserId,
                'seller_user_id' => $sellerUserId,
                'transaction_type' => $transactionType,
                'property_price' => $propertyPrice,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'notes' => $notes,
            ]);

            // Log activity
            Log::info('Commission created', [
                'commission_id' => $commission->id,
                'property_id' => $propertyId,
                'agent_id' => $agentUserId,
                'amount' => $commissionAmount,
                'type' => $transactionType,
            ]);

            // Create initial alert for pending approval
            CommissionAlert::create([
                'property_commission_id' => $commission->id,
                'agent_user_id' => $agentUserId,
                'alert_type' => 'pending_approval',
                'severity' => 'info',
                'message' => "New commission pending approval for property: {$property->title}. Amount: {$commissionAmount}",
                'status' => 'unread',
            ]);

            return $commission;
        });
    }

    /**
     * Validate transaction before processing
     * Ensures no unreported sales
     */
    public static function validateTransactionCommission($propertyId, $agentUserId, $transactionType = 'sale')
    {
        $property = Property::find($propertyId);
        if (!$property) {
            return [
                'valid' => false,
                'error' => 'Property not found',
                'code' => 'PROPERTY_NOT_FOUND',
            ];
        }

        $agent = User::find($agentUserId);
        if (!$agent || $agent->status !== 'active') {
            return [
                'valid' => false,
                'error' => 'Agent not found or inactive',
                'code' => 'INVALID_AGENT',
            ];
        }

        // Check if commission already exists for this transaction
        $existingCommission = PropertyCommission::where('property_id', $propertyId)
            ->where('agent_user_id', $agentUserId)
            ->where('transaction_type', $transactionType)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($existingCommission) {
            return [
                'valid' => false,
                'error' => 'Commission already recorded for this transaction',
                'code' => 'DUPLICATE_COMMISSION',
                'commission_id' => $existingCommission->id,
            ];
        }

        // Check commission settings
        $commissionSetting = CommissionSetting::getActive();
        if (!$commissionSetting) {
            return [
                'valid' => false,
                'error' => 'No active commission settings',
                'code' => 'NO_COMMISSION_SETTINGS',
            ];
        }

        if ($transactionType === 'sale' && !$commissionSetting->apply_to_sales) {
            return [
                'valid' => false,
                'error' => 'Sales commissions are disabled',
                'code' => 'SALES_DISABLED',
            ];
        }

        return [
            'valid' => true,
            'commission_amount' => $commissionSetting->calculateCommission($property->price),
            'commission_rate' => $commissionSetting->commission_type === 'percentage'
                ? $commissionSetting->commission_value
                : null,
        ];
    }

    /**
     * Process unpaid commissions alerts
     * Finds commissions unpaid for more than 30 days
     */
    public static function processOverdueAlerts()
    {
        $overdueCommissions = PropertyCommission::overdue()
            ->with('agent')
            ->get();

        foreach ($overdueCommissions as $commission) {
            // Check if alert already exists
            $existingAlert = CommissionAlert::where('property_commission_id', $commission->id)
                ->where('alert_type', 'overdue')
                ->where('status', '!=', 'resolved')
                ->first();

            if (!$existingAlert) {
                $daysOverdue = $commission->created_at->diffInDays(now());
                CommissionAlert::create([
                    'property_commission_id' => $commission->id,
                    'agent_user_id' => $commission->agent_user_id,
                    'alert_type' => 'overdue',
                    'severity' => $daysOverdue > 60 ? 'critical' : 'danger',
                    'message' => "Commission for property '{$commission->property->title}' is overdue by {$daysOverdue} days. Amount pending: {$commission->pending_amount}",
                    'status' => 'unread',
                ]);

                Log::warning('Overdue commission alert created', [
                    'commission_id' => $commission->id,
                    'agent_id' => $commission->agent_user_id,
                    'days_overdue' => $daysOverdue,
                    'pending_amount' => $commission->pending_amount,
                ]);
            }
        }
    }

    /**
     * Check for suspicious transactions (high commissions, multiple same day)
     */
    public static function checkSuspiciousTransactions($agentUserId)
    {
        // Get commissions from last 7 days for agent
        $recentCommissions = PropertyCommission::where('agent_user_id', $agentUserId)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('commission_amount', 'desc')
            ->get();

        $alerts = [];

        // Check for unusually high commissions
        if ($recentCommissions->isNotEmpty()) {
            $averageCommission = $recentCommissions->avg('commission_amount');
            $maxCommission = $recentCommissions->max('commission_amount');

            if ($maxCommission > ($averageCommission * 3)) {
                $alerts[] = [
                    'type' => 'high_commission',
                    'severity' => 'warning',
                    'message' => "Unusually high commission detected: {$maxCommission}",
                ];
            }

            // Check for multiple commissions in same day
            $commissionsByDay = $recentCommissions->groupBy(fn($c) => $c->created_at->format('Y-m-d'));
            if ($commissionsByDay->max('count') > 5) {
                $alerts[] = [
                    'type' => 'high_volume',
                    'severity' => 'warning',
                    'message' => 'Multiple commissions recorded in single day',
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get commission summary for dashboard
     */
    public static function getDashboardSummary($agentUserId = null)
    {
        $query = PropertyCommission::with('property', 'agent');

        if ($agentUserId) {
            $query->where('agent_user_id', $agentUserId);
        }

        $commissions = $query->get();

        return [
            'total_commissions' => $commissions->count(),
            'total_approved' => $commissions->where('status', 'approved')->count(),
            'total_pending' => $commissions->where('status', 'pending')->count(),
            'total_rejected' => $commissions->where('status', 'rejected')->count(),
            'total_earned' => $commissions->where('status', 'approved')->sum('commission_amount'),
            'total_paid' => $commissions->where('payment_status', 'paid')->sum('commission_amount'),
            'total_pending_payment' => $commissions->where('status', 'approved')
                ->where('payment_status', '!=', 'paid')
                ->sum(DB::raw('commission_amount - amount_paid')),
            'overdue_count' => PropertyCommission::overdue($agentUserId)->count(),
            'unpaid_alerts_count' => CommissionAlert::where('alert_type', 'unpaid')
                ->where('status', '!=', 'resolved')
                ->when($agentUserId, function ($q) use ($agentUserId) {
                    return $q->where('agent_user_id', $agentUserId);
                })
                ->count(),
        ];
    }

    /**
     * Get all pending commissions needing approval
     */
    public static function getPendingCommissions($limit = 10)
    {
        return PropertyCommission::pendingApproval()
            ->with('property', 'agent', 'buyer', 'seller')
            ->orderBy('created_at', 'asc')
            ->paginate($limit);
    }

    /**
     * Generate commission report
     */
    public static function generateReport($startDate, $endDate, $agentUserId = null)
    {
        $query = PropertyCommission::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->with('property', 'agent');

        if ($agentUserId) {
            $query->where('agent_user_id', $agentUserId);
        }

        $commissions = $query->get();

        return [
            'period' => "{$startDate} to {$endDate}",
            'total_transactions' => $commissions->count(),
            'by_type' => $commissions->groupBy('transaction_type')->map->count(),
            'by_status' => $commissions->groupBy('status')->map->count(),
            'by_payment_status' => $commissions->groupBy('payment_status')->map->count(),
            'total_commission_value' => $commissions->sum('commission_amount'),
            'total_paid' => $commissions->where('payment_status', 'paid')->sum('commission_amount'),
            'total_pending' => $commissions->where('status', 'approved')
                ->where('payment_status', '!=', 'paid')
                ->sum(DB::raw('commission_amount - amount_paid')),
            'commissions_by_agent' => $commissions->groupBy('agent_id')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('commission_amount'),
                    'paid' => $group->where('payment_status', 'paid')->sum('commission_amount'),
                ];
            }),
        ];
    }
}
