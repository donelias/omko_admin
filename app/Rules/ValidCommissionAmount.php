<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rule: Validate commission amount matches expected calculation
 */
class ValidCommissionAmount implements ValidationRule
{
    private $expectedAmount;
    private $tolerance;

    public function __construct($expectedAmount, $tolerance = 0.01)
    {
        $this->expectedAmount = (float)$expectedAmount;
        $this->tolerance = (float)$tolerance;
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $value = (float)$value;
        $difference = abs($value - $this->expectedAmount);

        if ($difference > $this->tolerance) {
            $fail("Commission amount must be {$this->expectedAmount} (within tolerance of {$this->tolerance}).");
        }
    }
}
