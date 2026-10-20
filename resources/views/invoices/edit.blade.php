@extends('layouts.main')

@section('title')
    {{ __('Edit Invoice') }} · {{ $invoice->invoice_number }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('dashboard') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('invoices.index') }}">{{ __('Invoices') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">@yield('title')</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-8">
                                <h4 class="card-title mb-0">{{ __('Invoice') }} {{ $invoice->invoice_number }}</h4>
                                <small class="text-muted">
                                    {{ __('Sequence') }}: {{ $invoice->formatted_number }}
                                    · {{ __('Issue date') }}: {{ $invoice->issue_date->format('d/m/Y') }}
                                </small>
                            </div>
                            <div class="col-12 col-md-4 d-flex justify-content-md-end mt-2 mt-md-0">
                                <a href="{{ route('invoices.preview', $invoice->id) }}" target="_blank" class="btn btn-outline-secondary me-2">
                                    <i class="fa fa-eye"></i> {{ __('Preview') }}
                                </a>
                                <a href="{{ route('invoices.download', $invoice->id) }}" class="btn btn-outline-primary">
                                    <i class="fa fa-file-pdf-o"></i> {{ __('Download PDF') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        {{-- El NCF no se puede reasignar, pero sí reenviarse --}}
                        <form action="{{ url('invoices') }}" id="resend-form" method="POST" class="d-none">
                            @csrf
                            <input type="hidden" name="id" value="{{ $invoice->id }}">
                        </form>

                        <div class="mb-3 d-flex justify-content-end">
                            <button type="button" class="btn btn-outline-success btn-sm"
                                onclick="resendInvoice()">
                                <i class="fa fa-paper-plane"></i> {{ __('Resend by email') }}
                            </button>
                        </div>

                        <form action="{{ url('invoices') }}"
                            class="form-horizontal edit-form edit-form-without-reset invoice-form"
                            method="POST"
                            data-success-function="invoiceFormSuccess"
                            autocomplete="off">
                            @csrf
                            {{ method_field('PUT') }}
                            <input type="hidden" name="edit_id" value="{{ $invoice->id }}">

                            <div class="row">
                                @include('invoices._form', ['transactions' => $transactions, 'currency' => $currency])
                            </div>

                            <hr>

                            <div class="d-flex justify-content-end">
                                <a href="{{ route('invoices.index') }}" class="btn btn-secondary me-2">{{ __('Cancel') }}</a>
                                <button type="submit" class="btn btn-primary" value="{{ __('Save') }}">
                                    {{ __('Save changes') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        function invoiceFormSuccess(response) {
            window.location.href = "{{ route('invoices.index') }}";
        }

        function resendInvoice() {
            $.ajax({
                url: "{{ route('invoices.resend', $invoice->id) }}",
                type: 'POST',
                data: { _token: "{{ csrf_token() }}" },
                success: function (response) {
                    if (response && response.error) {
                        alert(response.message || "{{ __('Error Occurred') }}");
                    } else {
                        alert(response.message || "{{ __('Email queued') }}");
                    }
                },
                error: function () {
                    alert("{{ __('Error Occurred') }}");
                },
            });
        }
    </script>
@endsection
