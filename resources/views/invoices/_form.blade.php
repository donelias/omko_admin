{{--
    Formulario de factura. Compartido por create y edit.

    El subtotal NUNCA se envía: lo calcula el servidor (quantity * unit_price)
    y los totales los deriva el modelo en su evento `saving`. Lo único que se
    muestra aquí es una vista previa en vivo para que cuadre antes de guardar.
--}}
@php
    $invoice = $invoice ?? null;
    $val = fn (string $field, $default = '') => old($field, $invoice ? $invoice->{$field} : $default);
@endphp

{{-- Venta de paquete vinculada (opcional) --}}
<div class="col-12 form-group">
    {{ Form::label('payment_transaction_id', __('Package sale (optional)'), ['class' => 'form-label']) }}
    <select name="payment_transaction_id" id="payment_transaction_id" class="form-select">
        <option value="">{{ __('— Invoice without linking —') }}</option>
        @foreach ($transactions as $transaction)
            <option value="{{ $transaction->id }}"
                @selected((string) old('payment_transaction_id', $invoice->payment_transaction_id ?? '') === (string) $transaction->id)>
                #{{ $transaction->id }}
                · {{ $transaction->package->name ?? __('Package') }}
                · {{ App\Models\Invoice::money($transaction->amount) }}
                · {{ optional($transaction->created_at)->format('d/m/Y') }}
            </option>
        @endforeach
    </select>
    <small class="text-muted">
        {{ __('Selecting a sale links the invoice and fills in the client when it is empty.') }}
    </small>
</div>

{{-- Comprobante fiscal. El número fiscal es inmutable una vez emitido: en
     edición el tipo queda fijado en el original y solo se muestra. --}}
@if ($invoice)
    <input type="hidden" name="ncf_type" value="{{ $invoice->ncf_type }}">
    <div class="col-md-4 form-group">
        {{ Form::label('ncf_type_display', __('NCF type'), ['class' => 'form-label']) }}
        {{ Form::text('ncf_type_display', $invoice->ncf_type, ['class' => 'form-control', 'disabled' => 'disabled', 'id' => 'ncf_type']) }}
        <small class="text-muted">{{ __('NCF') }}: <strong>{{ $invoice->invoice_number }}</strong></small>
    </div>
@else
    <div class="col-md-4 form-group mandatory">
        {{ Form::label('ncf_type', __('NCF type'), ['class' => 'form-label']) }}
        {{ Form::text('ncf_type', $val('ncf_type', 'B01'), [
            'class' => 'form-control',
            'placeholder' => 'B01',
            'maxlength' => 3,
            'data-parsley-required' => 'true',
            'data-parsley-pattern' => '/^[A-Za-z0-9]{3}$/',
            'id' => 'ncf_type',
        ]) }}
        <small class="text-muted">{{ __('Credit fiscal invoice normally uses B01.') }}</small>
    </div>
@endif

{{-- Estado --}}
<div class="col-md-4 form-group mandatory">
    {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
    <select name="status" id="status" class="form-select" data-parsley-required="true">
        @foreach (['emitida' => __('Issued'), 'pendiente' => __('Pending'), 'pagada' => __('Paid'), 'anulada' => __('Voided')] as $key => $label)
            <option value="{{ $key }}" @selected($val('status', 'emitida') === $key)>{{ $label }}</option>
        @endforeach
    </select>
</div>

{{-- Moneda --}}
<div class="col-md-4 form-group mandatory">
    {{ Form::label('currency', __('Currency'), ['class' => 'form-label']) }}
    <select name="currency" id="currency" class="form-select" data-parsley-required="true">
        @foreach (['RD', 'USD'] as $code)
            <option value="{{ $code }}" @selected($val('currency', $currency) === $code)>{{ $code }}</option>
        @endforeach
    </select>
</div>

{{-- Fechas --}}
<div class="col-md-6 form-group mandatory">
    {{ Form::label('issue_date', __('Issue date'), ['class' => 'form-label']) }}
    {{ Form::date('issue_date', $val('issue_date', date('Y-m-d')), [
        'class' => 'form-control',
        'data-parsley-required' => 'true',
        'id' => 'issue_date',
    ]) }}
</div>

<div class="col-md-6 form-group">
    {{ Form::label('due_date', __('Due date'), ['class' => 'form-label']) }}
    {{ Form::date('due_date', $val('due_date'), [
        'class' => 'form-control',
        'id' => 'due_date',
    ]) }}
</div>

{{-- Cliente --}}
<div class="col-12 form-group mandatory">
    {{ Form::label('client_name', __('Client'), ['class' => 'form-label']) }}
    {{ Form::text('client_name', $val('client_name'), [
        'class' => 'form-control',
        'placeholder' => __('Client name'),
        'data-parsley-required' => 'true',
        'id' => 'client_name',
    ]) }}
</div>

<div class="col-md-4 form-group">
    {{ Form::label('client_rnc', __('RNC / Cédula'), ['class' => 'form-label']) }}
    {{ Form::text('client_rnc', $val('client_rnc'), [
        'class' => 'form-control',
        'placeholder' => '401-00013-1',
        'maxlength' => 40,
        'id' => 'client_rnc',
    ]) }}
</div>

<div class="col-md-4 form-group">
    {{ Form::label('client_phone', __('Phone'), ['class' => 'form-label']) }}
    {{ Form::text('client_phone', $val('client_phone'), ['class' => 'form-control', 'maxlength' => 40]) }}
</div>

<div class="col-md-4 form-group">
    {{ Form::label('client_city', __('City'), ['class' => 'form-label']) }}
    {{ Form::text('client_city', $val('client_city'), ['class' => 'form-control', 'maxlength' => 120]) }}
</div>

<div class="col-md-8 form-group">
    {{ Form::label('client_address', __('Address'), ['class' => 'form-label']) }}
    {{ Form::text('client_address', $val('client_address'), ['class' => 'form-control', 'maxlength' => 255]) }}
</div>

<div class="col-md-4 form-group">
    {{ Form::label('client_email', __('Email'), ['class' => 'form-label']) }}
    {{ Form::email('client_email', $val('client_email'), [
        'class' => 'form-control',
        'maxlength' => 190,
        'id' => 'client_email',
    ]) }}
    <small class="text-muted">{{ __('The PDF is sent here.') }}</small>
</div>

{{-- Concepto y montos --}}
<div class="col-12 form-group mandatory">
    {{ Form::label('concept', __('Concept'), ['class' => 'form-label']) }}
    {{ Form::textarea('concept', $val('concept'), [
        'class' => 'form-control',
        'rows' => 3,
        'maxlength' => 1000,
        'placeholder' => __('Description of the service or sale being invoiced'),
        'data-parsley-required' => 'true',
        'id' => 'concept',
    ]) }}
</div>

<div class="col-md-3 form-group mandatory">
    {{ Form::label('quantity', __('Quantity'), ['class' => 'form-label']) }}
    {{ Form::number('quantity', $val('quantity', 1), [
        'class' => 'form-control',
        'min' => 1,
        'step' => 1,
        'data-parsley-required' => 'true',
        'data-parsley-type' => 'integer',
        'id' => 'quantity',
    ]) }}
</div>

<div class="col-md-3 form-group mandatory">
    {{ Form::label('unit_price', __('Unit price'), ['class' => 'form-label']) }}
    {{ Form::number('unit_price', $val('unit_price'), [
        'class' => 'form-control',
        'min' => 0,
        'step' => '0.01',
        'data-parsley-required' => 'true',
        'data-parsley-type' => 'number',
        'id' => 'unit_price',
    ]) }}
</div>

<div class="col-md-3 form-group mandatory">
    {{ Form::label('itbis_rate', __('ITBIS %'), ['class' => 'form-label']) }}
    {{ Form::number('itbis_rate', $val('itbis_rate', 18), [
        'class' => 'form-control',
        'min' => 0,
        'max' => 100,
        'step' => '0.01',
        'data-parsley-required' => 'true',
        'data-parsley-type' => 'number',
        'id' => 'itbis_rate',
    ]) }}
</div>

<div class="col-md-3 form-group">
    {{ Form::label('retention_rate', __('Retention of ITBIS %'), ['class' => 'form-label']) }}
    {{ Form::number('retention_rate', $val('retention_rate', 0), [
        'class' => 'form-control',
        'min' => 0,
        'max' => 100,
        'step' => '0.01',
        'id' => 'retention_rate',
    ]) }}
    <small class="text-muted">{{ __('Applied to the ITBIS, not the subtotal.') }}</small>
</div>

{{-- Vista previa de totales --}}
<div class="col-12">
    <div class="alert alert-light border mb-0" id="invoice-preview">
        <div class="row text-center">
            <div class="col">
                <small class="text-muted d-block">{{ __('Subtotal') }}</small>
                <strong id="pv-subtotal">0,00</strong>
            </div>
            <div class="col">
                <small class="text-muted d-block">{{ __('ITBIS') }}</small>
                <strong id="pv-itbis">0,00</strong>
            </div>
            <div class="col">
                <small class="text-muted d-block">{{ __('Retention') }}</small>
                <strong id="pv-retention">0,00</strong>
            </div>
            <div class="col">
                <small class="text-muted d-block">{{ __('Total to pay') }}</small>
                <strong class="text-success fs-5" id="pv-total">0,00</strong>
            </div>
        </div>
    </div>
</div>

{{-- Nota (datos bancarios / leyenda) --}}
<div class="col-12 form-group">
    {{ Form::label('notes', __('Note'), ['class' => 'form-label']) }}
    {{ Form::textarea('notes', $val('notes', 'Realizar cheques o transferencias a nombre de ' . (App\Services\HelperService::getSettingData('company_name') ?? 'OMKO') . '.'), [
        'class' => 'form-control',
        'rows' => 3,
        'maxlength' => 1000,
        'id' => 'notes',
    ]) }}
</div>

<script>
    (function () {
        function run() {
            // JS puro para el cálculo: no depende de jQuery ni de su timing de
            // carga. Si el DOM aún no está listo se reintenta.
            var qtyEl = document.getElementById('quantity');
            var unitEl = document.getElementById('unit_price');
            var itbisEl = document.getElementById('itbis_rate');
            var retEl = document.getElementById('retention_rate');
            var pvSub = document.getElementById('pv-subtotal');

            if (!qtyEl || !unitEl || !itbisEl || !retEl || !pvSub) {
                return false;
            }

            // Formato fiscal dominicano: punto de miles, coma decimal. Se hace
            // a mano porque toLocaleString depende del ICU del navegador.
            function money(n) {
                n = (Math.round(n * 100) / 100).toFixed(2);
                var parts = n.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                return parts.join(',');
            }

            function recalc() {
                var qty = parseFloat(qtyEl.value) || 0;
                var unit = parseFloat(unitEl.value) || 0;
                var itbisRate = parseFloat(itbisEl.value) || 0;
                var retentionRate = parseFloat(retEl.value) || 0;

                var subtotal = Math.round(qty * unit * 100) / 100;
                var itbis = Math.round(subtotal * itbisRate) / 100;
                // La retención se calcula SOBRE el ITBIS, igual que en el
                // comprobante de crédito fiscal.
                var retention = Math.round(itbis * retentionRate) / 100;
                var total = Math.round((subtotal + itbis - retention) * 100) / 100;

                pvSub.textContent = money(subtotal);
                document.getElementById('pv-itbis').textContent = money(itbis);
                document.getElementById('pv-retention').textContent = money(retention);
                document.getElementById('pv-total').textContent = money(total);
            }

            [qtyEl, unitEl, itbisEl, retEl].forEach(function (el) {
                el.addEventListener('input', recalc);
                el.addEventListener('change', recalc);
                el.addEventListener('keyup', recalc);
            });
            recalc();

            // Vincular una venta de paquete completa el concepto si está vacío
            // (el servidor hace lo mismo con el cliente al guardar).
            var txEl = document.getElementById('payment_transaction_id');
            var conceptEl = document.getElementById('concept');
            if (txEl && conceptEl) {
                txEl.addEventListener('change', function () {
                    if (!txEl.value || conceptEl.value) return;
                    var label = txEl.options[txEl.selectedIndex].text.split('·');
                    var pkg = (label[1] || '').trim();
                    if (pkg && pkg !== "{{ __('Package') }}") {
                        conceptEl.value = "{{ __('Package sale') }}: " + pkg;
                    }
                });
            }

            return true;
        }

        // jQuery Validate: las clases .create-form/.edit-form del panel tienen la
        // validación global COMENTADA en validate.js, por eso formAjaxRequest()
        // no envía el formulario. Aquí se inicializa explícitamente.
        function initValidation() {
            if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.validate) {
                return;
            }
            var form = window.jQuery('.invoice-form');
            if (form.length && !form.data('validator')) {
                form.validate({
                    ignore: [],
                    rules: {
                        ncf_type: { required: true },
                        status: { required: true },
                        currency: { required: true },
                        issue_date: { required: true },
                        client_name: { required: true },
                        concept: { required: true },
                        quantity: { required: true, digits: true },
                        unit_price: { required: true, number: true },
                        itbis_rate: { required: true, number: true },
                    },
                });
            }
        }

        function boot() {
            if (run()) {
                initValidation();
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
        // Segundo intento por si jQuery carga después (DOM ya listo).
        window.addEventListener('load', boot);
    })();
</script>
