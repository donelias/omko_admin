@extends('layouts.main')

@section('title')
    {{ __('Invoices') }}
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
                <div class="row align-items-center">
                    <div class="col-12 col-md-8">
                        <h4 class="card-title mb-0">{{ __('Invoices') }}</h4>
                        <small class="text-muted">
                            {{ __('Next NCF (B01)') }}: <strong>{{ $nextNcf }}</strong>
                        </small>
                    </div>
                    @if (has_permissions('create', 'invoice'))
                        <div class="col-12 col-md-4 d-flex justify-content-md-end mt-2 mt-md-0">
                            <a href="{{ route('invoices.create') }}" class="btn btn-primary">
                                <i class="fa fa-plus"></i> {{ __('Create Invoice') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card-body">
                {{-- Resumen rápido del periodo --}}
                <div class="row mb-3">
                    <div class="col-6 col-xl-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-0 text-muted small">{{ __('Total') }}</p>
                                        <h4 class="mb-0">{{ $stats['total'] }}</h4>
                                    </div>
                                    <span class="badge bg-primary">Σ</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-0 text-muted small">{{ __('Issued') }}</p>
                                        <h4 class="mb-0">{{ $stats['emitida'] }}</h4>
                                    </div>
                                    <span class="badge bg-info">✓</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-0 text-muted small">{{ __('Paid') }}</p>
                                        <h4 class="mb-0">{{ $stats['pagada'] }}</h4>
                                    </div>
                                    <span class="badge bg-success">$</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="card card-stats">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-0 text-muted small">{{ __('Voided') }}</p>
                                        <h4 class="mb-0">{{ $stats['anulada'] }}</h4>
                                    </div>
                                    <span class="badge bg-danger">✕</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Filtro por estado --}}
                <div class="mb-3">
                    <div class="btn-group" role="group" id="invoice-status-filter">
                        <button type="button" class="btn btn-outline-primary active" data-value="">{{ __('All') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="emitida">{{ __('Issued') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="pendiente">{{ __('Pending') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="pagada">{{ __('Paid') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="anulada">{{ __('Voided') }}</button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped" id="table_list" data-toggle="table"
                            data-url="{{ route('invoices.show', 1) }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-search-align="right"
                            data-trim-on-search="false" data-responsive="true" data-sort-name="id"
                            data-sort-order="desc" data-query-params="queryParams" data-show-refresh="true">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="invoice_number" data-sortable="true">{{ __('NCF') }}</th>
                                    <th scope="col" data-field="client_name" data-sortable="true">{{ __('Client') }}</th>
                                    <th scope="col" data-field="client_rnc" data-sortable="false">{{ __('RNC / Cédula') }}</th>
                                    <th scope="col" data-field="concept" data-sortable="false">{{ __('Concept') }}</th>
                                    <th scope="col" data-field="total_formatted" data-align="right"
                                        data-sortable="true">{{ __('Total') }}</th>
                                    <th scope="col" data-field="issue_date_formatted" data-align="center"
                                        data-sortable="true">{{ __('Issue Date') }}</th>
                                    <th scope="col" data-field="status" data-align="center" data-sortable="true"
                                        data-formatter="invoiceStatusFormatter">{{ __('Status') }}</th>
                                    @if (has_permissions('update', 'invoice') || has_permissions('delete', 'invoice'))
                                        <th scope="col" data-field="operate" data-align="center"
                                            data-sortable="false">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
                status: $('#invoice-status-filter button.active').data('value') || '',
            };
        }

        function invoiceStatusFormatter(value) {
            const map = {
                emitida: ['badge bg-primary', "{{ __('Issued') }}"],
                pendiente: ['badge bg-warning text-dark', "{{ __('Pending') }}"],
                pagada: ['badge bg-success', "{{ __('Paid') }}"],
                anulada: ['badge bg-danger', "{{ __('Voided') }}"],
            };
            const conf = map[value] || ['badge bg-secondary', value];
            return '<span class="' + conf[0] + '">' + conf[1] + '</span>';
        }

        $(function () {
            $('#invoice-status-filter button').on('click', function () {
                $('#invoice-status-filter button').removeClass('active');
                $(this).addClass('active');
                $('#table_list').bootstrapTable('refresh');
            });
        });
    </script>
@endsection
