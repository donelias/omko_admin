<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PreQualification;
use App\Models\PreQualificationDocument;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\PreQualificationNotificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PreQualificationApiController extends Controller
{
    public function store(Request $request)
    {
        $customer = auth()->user();

        if (!$customer) {
            return response()->json(['error' => true, 'message' => trans('Unauthenticated.')], 401);
        }

        $validator = Validator::make($request->all(), [
            'project_id' => 'nullable|exists:projects,id',
            'property_id' => 'nullable|exists:propertys,id',
            'financial_entity_type' => 'required|in:App\Models\Bank,App\Models\Cooperative',
            'financial_entity_id' => 'required|integer',
            'financial_advisor_id' => 'nullable|exists:bank_financial_advisors,id',
            'currency' => 'required|in:DOP,USD',
            'monthly_income' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'submitted_by' => 'nullable|exists:customers,id',
            'documents' => 'nullable|array',
            'documents.*.type' => 'required|in:bank_statements,paystubs,taxes,passport,license_id,other',
            'documents.*.file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();

            $preQualification = PreQualification::create([
                'customer_id' => $customer->id,
                'project_id' => $request->project_id,
                'property_id' => $request->property_id,
                'financial_entity_type' => $request->financial_entity_type,
                'financial_entity_id' => $request->financial_entity_id,
                'financial_advisor_id' => $request->financial_advisor_id,
                'currency' => $request->currency,
                'monthly_income' => $request->monthly_income,
                'notes' => $request->notes,
                'submitted_by' => $request->submitted_by,
                'status' => 'pending',
            ]);

            if ($request->has('documents')) {
                foreach ($request->documents as $doc) {
                    $file = $doc['file'];
                    $filename = FileService::compressAndUpload($file, config('global.PRE_QUALIFICATION_PATH'));
                    if ($filename) {
                        PreQualificationDocument::create([
                            'pre_qualification_id' => $preQualification->id,
                            'type' => $doc['type'],
                            'label' => $doc['label'] ?? null,
                            'file_path' => $filename,
                            'original_name' => $file->getClientOriginalName(),
                            'file_size' => $file->getSize(),
                        ]);
                    }
                }
            }

            DB::commit();

            $preQualification->load(['documents', 'customer', 'financialEntity', 'financialAdvisor']);

            try {
                PreQualificationNotificationService::sendNotifications($preQualification);
            } catch (Exception $e) {
                \Log::error('PreQualification notification error: ' . $e->getMessage());
            }

            return response()->json([
                'error' => false,
                'message' => trans('Pre-qualification submitted successfully. We will contact you soon.'),
                'data' => $this->formatPreQualification($preQualification),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => trans('Something went wrong. Please try again.'),
            ]);
        }
    }

    public function index(Request $request)
    {
        $customer = auth()->user();

        if (!$customer) {
            return response()->json(['error' => true, 'message' => trans('Unauthenticated.')], 401);
        }

        $preQualifications = PreQualification::forCustomer($customer->id)
            ->with(['documents', 'project', 'property', 'financialEntity', 'financialAdvisor'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($pq) {
                return $this->formatPreQualification($pq);
            });

        return response()->json([
            'error' => false,
            'data' => $preQualifications,
        ]);
    }

    public function show($id)
    {
        $customer = auth()->user();

        if (!$customer) {
            return response()->json(['error' => true, 'message' => trans('Unauthenticated.')], 401);
        }

        $preQualification = PreQualification::forCustomer($customer->id)
            ->with(['documents', 'project', 'property', 'financialEntity', 'financialAdvisor'])
            ->findOrFail($id);

        return response()->json([
            'error' => false,
            'data' => $this->formatPreQualification($preQualification),
        ]);
    }

    public function agentStore(Request $request)
    {
        $agent = auth()->user();

        if (!$agent || !$request->has('submitted_by')) {
            return response()->json(['error' => true, 'message' => trans('Unauthorized.')], 403);
        }

        $request->merge(['submitted_by' => $agent->id]);
        return $this->store($request);
    }

    private function formatPreQualification($pq)
    {
        $entity = $pq->financialEntity;
        return [
            'id' => $pq->id,
            'status' => $pq->status,
            'currency' => $pq->currency,
            'monthly_income' => $pq->monthly_income,
            'notes' => $pq->notes,
            'created_at' => $pq->created_at,
            'project' => $pq->project ? [
                'id' => $pq->project->id,
                'title' => $pq->project->title,
                'slug_id' => $pq->project->slug_id,
            ] : null,
            'property' => $pq->property ? [
                'id' => $pq->property->id,
                'title' => $pq->property->title,
                'slug_id' => $pq->property->slug_id,
            ] : null,
            'financial_entity' => $entity ? [
                'id' => $entity->id,
                'name' => $entity->name,
                'interest_rate' => $entity->interest_rate ?? null,
                'type' => $pq->financial_entity_type === 'App\Models\Bank' ? 'bank' : 'cooperative',
            ] : null,
            'financial_advisor' => $pq->financialAdvisor ? [
                'id' => $pq->financialAdvisor->id,
                'name' => $pq->financialAdvisor->name,
                'email' => $pq->financialAdvisor->email,
            ] : null,
            'submitted_by' => $pq->submittedBy ? [
                'id' => $pq->submittedBy->id,
                'name' => $pq->submittedBy->name ?? $pq->submittedBy->first_name . ' ' . $pq->submittedBy->last_name,
            ] : null,
            'documents' => $pq->documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type' => $doc->type,
                    'label' => $doc->label,
                    'url' => FileService::getFileUrl(config('global.PRE_QUALIFICATION_PATH') . $doc->file_path),
                    'original_name' => $doc->original_name,
                    'file_size' => $doc->file_size,
                ];
            }),
        ];
    }
}
