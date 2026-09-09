<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankFinancialAdvisor;
use App\Models\Cooperative;
use Illuminate\Http\Request;

class FinancialEntityApiController extends Controller
{
    public function banks(Request $request)
    {
        $banks = Bank::active()
            ->ordered()
            ->whereHas('financialAdvisors', function ($q) {
                $q->active()->whereNotNull('email')->where('email', '!=', '');
            })
            ->with(['primaryAdvisor'])
            ->get()
            ->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'interest_rate' => $bank->interest_rate,
                    'currency' => $bank->currency,
                    'email' => $bank->email,
                    'phone' => $bank->phone,
                    'website' => $bank->website,
                    'description' => $bank->description,
                    'primary_advisor' => $bank->primaryAdvisor ? [
                        'id' => $bank->primaryAdvisor->id,
                        'name' => $bank->primaryAdvisor->name,
                        'email' => $bank->primaryAdvisor->email,
                        'phone' => $bank->primaryAdvisor->phone,
                    ] : null,
                ];
            });

        return response()->json([
            'error' => false,
            'data' => $banks,
        ]);
    }

    public function cooperatives(Request $request)
    {
        $cooperatives = Cooperative::active()
            ->ordered()
            ->whereHas('financialAdvisors', function ($q) {
                $q->active()->whereNotNull('email')->where('email', '!=', '');
            })
            ->with(['primaryAdvisor'])
            ->get()
            ->map(function ($coop) {
                return [
                    'id' => $coop->id,
                    'name' => $coop->name,
                    'interest_rate' => $coop->interest_rate,
                    'currency' => $coop->currency,
                    'email' => $coop->email,
                    'phone' => $coop->phone,
                    'website' => $coop->website,
                    'description' => $coop->description,
                    'primary_advisor' => $coop->primaryAdvisor ? [
                        'id' => $coop->primaryAdvisor->id,
                        'name' => $coop->primaryAdvisor->name,
                        'email' => $coop->primaryAdvisor->email,
                        'phone' => $coop->primaryAdvisor->phone,
                    ] : null,
                ];
            });

        return response()->json([
            'error' => false,
            'data' => $cooperatives,
        ]);
    }

    public function advisors(Request $request)
    {
        $entityType = $request->input('entity_type');
        $entityId = $request->input('entity_id');

        $query = BankFinancialAdvisor::active();

        if ($entityType && $entityId) {
            $query->where('entity_type', $entityType)->where('entity_id', $entityId);
        }

        $advisors = $query->get()->map(function ($advisor) {
            return [
                'id' => $advisor->id,
                'name' => $advisor->name,
                'email' => $advisor->email,
                'phone' => $advisor->phone,
                'is_primary' => $advisor->is_primary,
            ];
        });

        return response()->json([
            'error' => false,
            'data' => $advisors,
        ]);
    }
}
