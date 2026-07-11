<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BankFinancialAdvisor;
use App\Models\Cooperative;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinancialAdvisorController extends Controller
{
    public function index()
    {
        if (! has_permissions('read', 'financial_advisor')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $banks = Bank::active()->ordered()->get();
        $cooperatives = Cooperative::active()->ordered()->get();
        return view('financial-advisors.index', compact('banks', 'cooperatives'));
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'financial_advisor')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'entity_type' => 'required|in:App\Models\Bank,App\Models\Cooperative',
            'entity_id' => 'required|integer',
            'is_primary' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            if ($request->boolean('is_primary')) {
                BankFinancialAdvisor::where('entity_type', $request->entity_type)
                    ->where('entity_id', $request->entity_id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
            BankFinancialAdvisor::create($request->all());
            ResponseService::successResponse(trans('Data Created Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function show(string $id)
    {
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');
        $entityType = request('entity_type');
        $entityId = request('entity_id');

        $sql = BankFinancialAdvisor::with('entity')
            ->when($entityType, function ($q) use ($entityType) {
                $q->where('entity_type', $entityType);
            })
            ->when($entityId, function ($q) use ($entityId) {
                $q->where('entity_id', $entityId);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%")
                        ->orWhere('email', 'LIKE', "%$search%");
                });
            });

        $total = $sql->count();
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = ['total' => $total, 'rows' => []];
        $no = 1;
        foreach ($res as $row) {
            $operate = '';
            if (has_permissions('update', 'financial_advisor')) {
                $operate .= BootstrapTableService::editButton('', true, null, null, null, null);
            }
            if (has_permissions('delete', 'financial_advisor')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('financial-advisors.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['entity_name'] = $row->entity ? $row->entity->name : '-';
            $tempRow['entity_type_label'] = $row->entity_type === 'App\Models\Bank' ? 'Bank' : 'Cooperative';
            if (has_permissions('update', 'financial_advisor')) {
                $tempRow['edit_status_url'] = route('financial-advisors.status-update');
            } else {
                $tempRow['edit_status_url'] = null;
            }
            $tempRow['operate'] = $operate;
            $bulkData['rows'][] = $tempRow;
        }

        return response()->json($bulkData);
    }

    public function update(Request $request, string $id)
    {
        if (! has_permissions('update', 'financial_advisor')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'edit_name' => 'required|max:255',
            'edit_email' => 'required|email|max:255',
            'edit_is_primary' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $advisor = BankFinancialAdvisor::findOrFail($id);
            if ($request->boolean('edit_is_primary')) {
                BankFinancialAdvisor::where('entity_type', $advisor->entity_type)
                    ->where('entity_id', $advisor->entity_id)
                    ->where('is_primary', true)
                    ->where('id', '!=', $id)
                    ->update(['is_primary' => false]);
            }
            $advisor->update([
                'name' => $request->edit_name,
                'email' => $request->edit_email,
                'phone' => $request->edit_phone,
                'is_primary' => $request->boolean('edit_is_primary'),
            ]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function destroy(string $id)
    {
        if (! has_permissions('delete', 'financial_advisor')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            BankFinancialAdvisor::where('id', $id)->delete();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function statusUpdate(Request $request)
    {
        if (! has_permissions('update', 'financial_advisor')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'status' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            BankFinancialAdvisor::where('id', $request->id)->update(['is_active' => $request->status]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }
}
