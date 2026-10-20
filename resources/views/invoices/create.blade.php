@extends('layouts.main')

@section('title')
    {{ __('Create Invoice') }}
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
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('New Invoice') }}</h4>
            </div>

            <div class="card-body">
                <form action="{{ route('invoices.store') }}"
                    class="form-horizontal create-form create-form-without-reset invoice-form"
                    method="POST"
                    data-success-function="invoiceFormSuccess"
                    autocomplete="off">
                    @csrf

                    <div class="row">
                        @include('invoices._form', ['invoice' => null, 'transactions' => $transactions, 'currency' => $currency])
                    </div>

                    <hr>

                    <div class="d-flex justify-content-end">
                        <a href="{{ route('invoices.index') }}" class="btn btn-secondary me-2">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary" value="{{ __('Save') }}">
                            {{ __('Create Invoice') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
        // Volver al listado: successCallback de common.js ya refresca la
        // tabla, pero estamos en otra página, así que se redirige.
        function invoiceFormSuccess(response) {
            window.location.href = "{{ route('invoices.index') }}";
        }
    </script>
@endsection
