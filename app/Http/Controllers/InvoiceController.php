<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\InvoiceNotificationService;
use App\Services\InvoiceService;
use App\Services\PDF\InvoicePdfService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    /**
     * Panel de facturación: listado con bootstrap-table.
     */
    public function index(Request $request)
    {
        if (! has_permissions('read', 'invoice')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $stats = [
            'total' => Invoice::count(),
            'emitida' => Invoice::where('status', 'emitida')->count(),
            'pagada' => Invoice::where('status', 'pagada')->count(),
            'anulada' => Invoice::where('status', 'anulada')->count(),
        ];

        // Recibo del próximo NCF para que el formulario muestre lo que se asignará.
        $nextNcf = Invoice::formatNcf(
            'B01',
            (int) (\App\Models\InvoiceSequence::where('ncf_type', 'B01')->value('next_sequence') ?? 1)
        );

        $currency = HelperService::getSettingData('currency_code') ?: 'RD';

        return view('invoices.index', compact('stats', 'nextNcf', 'currency'));
    }

    /**
     * JSON que alimenta la bootstrap-table (misma forma que `package.show`:
     * {total, rows[]}).
     */
    public function show(Request $request)
    {
        if (! has_permissions('read', 'invoice')) {
            return response()->json(['total' => 0, 'rows' => []]);
        }

        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 10);
        $sort = in_array($request->input('sort'), ['id', 'invoice_number', 'client_name', 'total', 'issue_date', 'status'], true)
            ? $request->input('sort') : 'id';
        $order = strtoupper((string) $request->input('order')) === 'ASC' ? 'ASC' : 'DESC';

        $sql = Invoice::query();

        if ($request->filled('status')) {
            $sql->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $sql->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('client_name', 'LIKE', "%{$search}%")
                    ->orWhere('client_rnc', 'LIKE', "%{$search}%")
                    ->orWhere('concept', 'LIKE', "%{$search}%");
            });
        }

        $total = (clone $sql)->count();
        $rows = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $data = [];
        foreach ($rows as $row) {
            $row = $row->toArray();
            $operate = '';
            if (has_permissions('update', 'invoice')) {
                $operate .= BootstrapTableService::editButton(route('invoices.edit', $row['id']));
            }
            // Enlaces normales: previsualizar (abre el PDF en pestaña nueva) y
            // descargar. No pasan por el handler AJAX de la tabla.
            $operate .= '<a class="btn icon btn-secondary btn-sm rounded-pill border border-secondary" title="'
                .trans('Preview').'" target="_blank" href="'.route('invoices.preview', $row['id']).'">'
                .'<i class="fa fa-eye"></i></a> ';
            $operate .= '<a class="btn icon btn-info btn-sm rounded-pill border border-info" title="'
                .trans('Download').'" href="'.route('invoices.download', $row['id']).'">'
                .'<i class="fa fa-file-pdf-o"></i></a> ';
            if (has_permissions('delete', 'invoice')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('invoices.destroy', $row['id']));
            }
            $row['operate'] = $operate;
            $row['total_formatted'] = Invoice::money($row['total']);
            $row['issue_date_formatted'] = substr((string) $row['issue_date'], 0, 10);
            $data[] = $row;
        }

        return response()->json(['total' => $total, 'rows' => $data]);
    }

    /**
     * Formulario de emisión.
     */
    public function create(Request $request)
    {
        if (! has_permissions('create', 'invoice')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $transaction = null;
        if ($request->filled('transaction')) {
            $transaction = PaymentTransaction::with('package', 'customer')->find($request->transaction);
        }

        $transactions = PaymentTransaction::with('package')
            ->where('payment_status', 'completed')
            ->latest()->limit(100)->get();

        $currency = HelperService::getSettingData('currency_code') ?: 'RD';

        return view('invoices.create', compact('transaction', 'transactions', 'currency'));
    }

    /**
     * Persiste una factura nueva reservando su NCF.
     */
    public function store(Request $request)
    {
        if (! has_permissions('create', 'invoice')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            $payload = $this->payload($request);

            // Vinculación opcional con una venta de paquete: si el formulario
            // llegó sin cliente, se rellena con el de la transacción.
            if ($transaction = $this->findTransaction($request)) {
                $payload['payment_transaction_id'] = $transaction->id;
                $payload = $this->prefillFromTransaction($payload, $transaction);
            }

            $payload['created_by'] = Auth::id();
            $payload = InvoiceService::rebuildSubtotal($payload);

            $invoice = app(InvoiceService::class)->emit($payload);

            $sent = InvoiceNotificationService::send($invoice);

            return ResponseService::successResponse(
                __('Invoice').' '.$invoice->invoice_number.' — '.__('created successfully').($sent ? ' ('.__('email queued').')' : ''),
                null,
                ['redirect_url' => route('invoices.index')]
            );
        } catch (\Throwable $e) {
            report($e);

            return ResponseService::errorResponse(__('Error Occurred'), null, null, $e);
        }
    }

    /**
     * Formulario de edición (el NCF no se toca).
     */
    public function edit($id)
    {
        if (! has_permissions('update', 'invoice')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $invoice = Invoice::findOrFail($id);
        $transactions = PaymentTransaction::with('package')
            ->where('payment_status', 'completed')
            ->latest()->limit(100)->get();
        $currency = HelperService::getSettingData('currency_code') ?: 'RD';

        return view('invoices.edit', compact('invoice', 'transactions', 'currency'));
    }

    /**
     * Actualiza y reenvía el correo con el PDF regenerado.
     */
    public function update($id, Request $request)
    {
        if (! has_permissions('update', 'invoice')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $invoice = Invoice::findOrFail($id);

        $validator = Validator::make($request->all(), $this->rules($invoice->id));
        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            $payload = $this->payload($request);
            $payload['payment_transaction_id'] = $invoice->payment_transaction_id;

            // Igual que en store: el subtotal se deriva de cantidad × precio
            // antes de guardar (el modelo recalcula itbis/retención/total).
            $payload = InvoiceService::rebuildSubtotal($payload);

            $invoice->update($payload);

            // El PDF se genera otra vez con los datos ya guardados, así que
            // lo que recibe el cliente es exactamente lo que hay en BD.
            InvoiceNotificationService::send($invoice);

            return ResponseService::successResponse(
                __('updated successfully'),
                null,
                ['redirect_url' => route('invoices.index')]
            );
        } catch (\Throwable $e) {
            report($e);

            return ResponseService::errorResponse(__('Error Occurred'), null, null, $e);
        }
    }

    /**
     * Cambia el estado (emitida / pagada / anulada / pendiente).
     */
    public function status(Request $request)
    {
        if (! has_permissions('update', 'invoice')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:invoices,id',
            'status' => 'required|in:emitida,pendiente,pagada,anulada',
        ]);
        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        $invoice = Invoice::findOrFail($request->id);
        $invoice->status = $request->status;
        $invoice->save();

        return ResponseService::successResponse(__('updated successfully'));
    }

    /**
     * Vuelve a enviar la factura por correo con el PDF adjunto.
     */
    public function resend($id)
    {
        if (! has_permissions('update', 'invoice')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $invoice = Invoice::findOrFail($id);
        $sent = InvoiceNotificationService::send($invoice);

        if ($sent > 0) {
            return ResponseService::successResponse(__('Email queued'));
        }

        return ResponseService::errorResponse(__('Error Occurred'));
    }

    /**
     * Descarga el PDF.
     */
    public function download($id)
    {
        if (! has_permissions('read', 'invoice')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return app(InvoicePdfService::class)->downloadPDF(Invoice::findOrFail($id));
    }

    /**
     * PDF en pantalla, para revisar antes de imprimir o enviar.
     */
    public function preview($id)
    {
        if (! has_permissions('read', 'invoice')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return app(InvoicePdfService::class)->streamPDF(Invoice::findOrFail($id));
    }

    /**
     * Borra. Solo tiene sentido si la factura no se emitió ante DGII:
     * anularla es la vía correcta, no eliminarla.
     */
    public function destroy($id)
    {
        if (! has_permissions('delete', 'invoice')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        Invoice::findOrFail($id)->delete();

        return ResponseService::successResponse(__('deleted successfully'));
    }

    /**
     * @param  int|null  $ignoreId  factura que se está editando (para excluirla
     *                              de reglas únicas, si se añadieran)
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'ncf_type' => 'required|string|size:3|alpha_num',
            'client_name' => 'required|string|max:255',
            'client_rnc' => 'nullable|string|max:40',
            'client_phone' => 'nullable|string|max:40',
            'client_city' => 'nullable|string|max:120',
            'client_address' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:190',
            'concept' => 'required|string|max:1000',
            'quantity' => 'required|integer|min:1|max:1000000',
            'unit_price' => 'required|numeric|min:0|max:999999999',
            'itbis_rate' => 'required|numeric|between:0,100',
            'retention_rate' => 'required|nullable|numeric|between:0,100',
            'currency' => 'required|string|max:10',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'status' => 'required|in:emitida,pendiente,pagada,anulada',
            'notes' => 'nullable|string|max:1000',
            'payment_transaction_id' => 'nullable|exists:payment_transactions,id',
        ];
    }

    /**
     * Todo salvo los importes: subtotal/totales los derivan el modelo y el
     * NCF no procede del formulario.
     */
    private function payload(Request $request): array
    {
        $data = $request->only([
            'ncf_type', 'client_name', 'client_rnc', 'client_phone', 'client_city',
            'client_address', 'client_email', 'concept', 'quantity', 'unit_price',
            'itbis_rate', 'currency', 'issue_date', 'due_date', 'status', 'notes',
        ]);

        $data['retention_rate'] = (float) ($request->input('retention_rate') ?: 0);

        return $data;
    }

    private function findTransaction(Request $request): ?PaymentTransaction
    {
        if (! $request->filled('payment_transaction_id')) {
            return null;
        }

        return PaymentTransaction::with('package', 'customer')->find($request->payment_transaction_id);
    }

    /**
     * Rellena el cliente y el concepto desde la venta de paquete cuando el
     * formulario dejó esos campos vacíos.
     */
    private function prefillFromTransaction(array $payload, PaymentTransaction $transaction): array
    {
        $customer = $transaction->customer;

        if (empty($payload['client_name']) && $customer) {
            $payload['client_name'] = $customer->name ?? '';
            $payload['client_rnc'] = $payload['client_rnc'] ?? '';
            $payload['client_email'] = $payload['client_email'] ?? ($customer->email ?? '');
            $payload['client_phone'] = $payload['client_phone'] ?? ($customer->full_mobile ?? ($customer->mobile ?? ''));
            $payload['client_city'] = $payload['client_city'] ?? ($customer->city ?? '');
            $payload['client_address'] = $payload['client_address'] ?? ($customer->address ?? '');
        }

        if (empty($payload['concept']) && $transaction->package) {
            $payload['concept'] = 'Venta de paquete: '.$transaction->package->name;
        }

        return $payload;
    }
}
