<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankController extends Controller
{
    public function index()
    {
        if (! has_permissions('read', 'bank')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        return view('banks.index');
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'bank')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'interest_rate' => 'nullable|numeric|min:0|max:99.99',
            'currency' => 'required|string|size:3',
            'email' => 'nullable|email|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            Bank::create($request->all());
            ResponseService::successResponse(trans('Data Created Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function show(string $id)
    {
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'sort_order');
        $order = request('order', 'ASC');
        $search = request('search');

        $sql = Bank::when($search, function ($query) use ($search) {
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
            if (has_permissions('update', 'bank')) {
                $operate .= BootstrapTableService::editButton('', true, null, null, null, null);
            }
            if (has_permissions('delete', 'bank')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('banks.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            if (has_permissions('update', 'bank')) {
                $tempRow['edit_status_url'] = route('banks.status-update');
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
        if (! has_permissions('update', 'bank')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'edit_name' => 'required|max:255',
            'edit_interest_rate' => 'nullable|numeric|min:0|max:99.99',
            'edit_currency' => 'required|string|size:3',
            'edit_email' => 'nullable|email|max:255',
            'edit_sort_order' => 'nullable|integer|min:0',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            Bank::where('id', $id)->update([
                'name' => $request->edit_name,
                'interest_rate' => $request->edit_interest_rate,
                'currency' => $request->edit_currency,
                'email' => $request->edit_email,
                'phone' => $request->edit_phone,
                'website' => $request->edit_website,
                'description' => $request->edit_description,
                'sort_order' => $request->edit_sort_order ?? 0,
            ]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function destroy(string $id)
    {
        if (! has_permissions('delete', 'bank')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Bank::where('id', $id)->delete();
            ResponseService::successResponse(trans('Data Deleted Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function statusUpdate(Request $request)
    {
        if (! has_permissions('update', 'bank')) {
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
            Bank::where('id', $request->id)->update(['is_active' => $request->status]);
            ResponseService::successResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }
}
