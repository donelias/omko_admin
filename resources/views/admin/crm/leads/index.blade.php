@extends('layouts.main')

@section('title')
    {{ __('Leads / CRM') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('home') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Leads / CRM') }}</li>
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
                        <h5 class="card-title">{{ __('Embudo de Leads') }}</h5>
                        <div class="card-tools">
                            <span class="badge bg-primary">{{ $total }} {{ __('total') }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($funnel as $status => $item)
                                <div class="col-6 col-md-3 col-xl-2 mb-3">
                                    <div class="card border-0 shadow-sm h-100 text-center">
                                        <div class="card-body py-3">
                                            <h5 class="mb-1">{{ $item['total'] }}</h5>
                                            <span class="badge bg-light text-dark text-wrap">{{ $item['label'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (has_permissions('read', 'crm_leads_reports'))
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">{{ __('Conversión por canal / origen') }}</h5>
                        </div>
                        <div class="card-body">
                            <div id="origin_chart"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">{{ __('Leads Management') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3" id="toolbar">
                            @if (has_permissions('read', 'crm_leads'))
                                <div class="col-12 col-md-4 mb-2">
                                    <select class="form-select" id="filter_status">
                                        <option value="all">{{ __('Todos los estados') }}</option>
                                        @foreach ($funnel as $status => $item)
                                            <option value="{{ $status }}">{{ $item['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-4 mb-2">
                                    <select class="form-select" id="filter_origin">
                                        <option value="all">{{ __('Todos los canales') }}</option>
                                        @foreach ($originSeries as $o)
                                            <option value="{{ $o['key'] }}">{{ $o['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-4 mb-2">
                                    <select class="form-select" id="filter_agent">
                                        <option value="all">{{ __('Todos los agentes') }}</option>
                                        @foreach ($agents as $a)
                                            <option value="{{ $a->id }}">{{ $a->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                        <table class="table table-striped"
                            id="table_list" data-toggle="table" data-url="{{ route('admin.crm.leads.list') }}"
                            data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                            data-search-align="right" data-toolbar="#toolbar" data-show-columns="true"
                            data-show-refresh="true" data-trim-on-search="false" data-responsive="true"
                            data-sort-name="id" data-sort-order="desc" data-pagination-successively-size="3">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="nombre" data-sortable="true">{{ __('Name') }}</th>
                                    <th scope="col" data-field="contact" data-sortable="false">{{ __('Contact') }}</th>
                                    <th scope="col" data-field="property" data-sortable="false">{{ __('Property') }}</th>
                                    <th scope="col" data-field="agent" data-sortable="false">{{ __('Agent') }}</th>
                                    <th scope="col" data-field="status" data-sortable="true">{{ __('Status') }}</th>
                                    <th scope="col" data-field="origin" data-sortable="true">{{ __('Origin') }}</th>
                                    <th scope="col" data-field="score" data-sortable="true">{{ __('Score') }}</th>
                                    <th scope="col" data-field="created_at" data-sortable="true">{{ __('Created') }}</th>
                                    <th scope="col" data-field="operate" data-align="center" data-sortable="false" data-events="actionEvents">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="leadDetailModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Detalle del Lead') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="leadDetailBody"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="statusModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Cambiar estado') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="statusForm">
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <input type="hidden" id="status_lead_id" name="id">
                        <div class="form-group">
                            <label for="status_select">{{ __('Estado') }}</label>
                            <select class="form-select" id="status_select" name="status">
                                @foreach ($funnel as $status => $item)
                                    <option value="{{ $status }}">{{ $item['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    @if (has_permissions('read', 'crm_leads_reports'))
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        <script>
            var originLabels = @json(array_column($originSeries, 'label'));
            var originValues = @json(array_column($originSeries, 'total'));
            var originOptions = {
                chart: { type: 'bar', height: 320, toolbar: { show: false } },
                plotOptions: { bar: { columnWidth: '50%' } },
                series: [{ name: '{{ __("Leads") }}', data: originValues }],
                xaxis: { categories: originLabels },
                colors: ['#435ebe'],
                tooltip: { y: { formatter: function(v) { return v + ' leads'; } } },
            };
            var originChart = new ApexCharts(document.querySelector("#origin_chart"), originOptions);
            originChart.render();
        </script>
    @endif

    <script>
        window.actionEvents = {
            'click .show-lead': function(e, value, row, index) {
                $.ajax({
                    url: "{{ route('admin.crm.leads.show') }}?id=" + row.id,
                    type: "GET",
                    success: function(response) {
                        if (response.error === false) {
                            var d = response.data;
                            var html = '<h6><strong>{{ __("Name") }}:</strong> ' + d.nombre + '</h6>';
                            html += '<p><strong>{{ __("Email") }}:</strong> ' + (d.email || '-') + '</p>';
                            html += '<p><strong>{{ __("Teléfono") }}:</strong> ' + (d.telefono || '-') + '</p>';
                            html += '<p><strong>{{ __("WhatsApp") }}:</strong> ' + (d.whatsapp || '-') + '</p>';
                            html += '<p><strong>{{ __("Property") }}:</strong> ' + d.property + '</p>';
                            html += '<p><strong>{{ __("Agent") }}:</strong> ' + d.agent + '</p>';
                            html += '<p><strong>{{ __("Status") }}:</strong> ' + d.status + ' &nbsp; <strong>{{ __("Origin") }}:</strong> ' + d.origin + '</p>';
                            html += '<p><strong>{{ __("Score") }}:</strong> ' + d.score + (d.recomendacion ? ' (' + d.recomendacion + ')' : '') + '</p>';
                            html += '<p><strong>Fecha:</strong> ' + d.created_at + '</p>';
                            if (d.interactions && d.interactions.length) {
                                html += '<hr><h6>{{ __("Interacciones") }}</h6><ul class="list-group">';
                                d.interactions.forEach(function(i) {
                                    html += '<li class="list-group-item"><strong>' + i.type + ':</strong> ' + i.contenido + '<br><small class="text-muted">' + i.created_at + '</small></li>';
                                });
                                html += '</ul>';
                            }
                            $('#leadDetailBody').html(html);
                            $('#leadDetailModal').modal('show');
                        } else {
                            Toastify({ text: response.message, duration: 5000, close: true, backgroundColor: '#dc3545' }).showToast();
                        }
                    }
                });
            },
            'click .change-status': function(e, value, row, index) {
                $('#status_lead_id').val(row.id);
                $('#status_select').val(row.status_raw || '');
                $('#statusModal').modal('show');
            },
            'click .delete-lead': function(e, value, row, index) {
                swal.fire({
                    title: '{{ __("Are you sure?") }}',
                    text: '{{ __("You will delete this lead") }}',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '{{ __("Yes") }}',
                    cancelButtonText: '{{ __("No") }}',
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('admin.crm.leads.destroy', ':id') }}".replace(':id', row.id),
                            type: "DELETE",
                            data: { _token: "{{ csrf_token() }}" },
                            success: function(response) {
                                Toastify({ text: response.message, duration: 5000, close: true, backgroundColor: response.error === false ? "linear-gradient(to right, #00b09b, #96c93d)" : '#dc3545' }).showToast();
                                $('#table_list').bootstrapTable('refresh');
                            }
                        });
                    }
                });
            }
        };

        $('#statusForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: "{{ route('admin.crm.leads.update-status') }}",
                type: "POST",
                data: $(this).serialize(),
                success: function(response) {
                    Toastify({
                        text: response.message,
                        duration: 5000,
                        close: true,
                        backgroundColor: response.error === false ? "linear-gradient(to right, #00b09b, #96c93d)" : '#dc3545'
                    }).showToast();
                    $('#statusModal').modal('hide');
                    $('#table_list').bootstrapTable('refresh');
                }
            });
        });

        $('#filter_status').on('change', function() { $('#table_list').bootstrapTable('refresh'); });
        $('#filter_origin').on('change', function() { $('#table_list').bootstrapTable('refresh'); });
        $('#filter_agent').on('change', function() { $('#table_list').bootstrapTable('refresh'); });

        $('#table_list').on('load-success.bs.table', function(e, data) {
            $(this).bootstrapTable('getSelections');
        });

        $('#table_list').on('queryParams.bs.table', function(e, params) {
            params.status = $('#filter_status').val();
            params.origin = $('#filter_origin').val();
            params.agent_id = $('#filter_agent').val();
            return params;
        });
    </script>
@endsection
