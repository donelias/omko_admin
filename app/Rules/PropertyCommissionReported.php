<?php

namespace App\Rules;

use App\Models\PropertyCommission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rule: Validate that property transaction has reported commission
 */
class PropertyCommissionReported implements ValidationRule
{
    private $propertyId;
    private $agentId;
    private $transactionType;

    public function __construct($propertyId, $agentId, $transactionType = 'sale')
    {
        $this->propertyId = $propertyId;
        $this->agentId = $agentId;
        $this->transactionType = $transactionType;
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        // Check if commission exists for this transaction
        $commission = PropertyCommission::where('property_id', $this->propertyId)
            ->where('agent_user_id', $this->agentId)
            ->where('transaction_type', $this->transactionType)
            ->where('status', 'approved')
            ->first();

        if (!$commission) {
            $fail("Commission must be reported and approved before completing this transaction.");
        }
    }
}
