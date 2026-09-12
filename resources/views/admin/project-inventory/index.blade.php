@extends('layouts.main')

@section('title')
    {{ __('On-Plan Inventory') }}
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
                        <li class="breadcrumb-item active" aria-current="page">{{ __('On-Plan') }}</li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Inventory') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped" id="table_list"
                            data-toggle="table" data-url="{{ route('admin.project-inventory.list') }}"
                            data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                            data-search-align="right" data-toolbar="#toolbar" data-show-columns="true"
                            data-show-refresh="true" data-trim-on-search="false" data-responsive="true"
                            data-sort-name="id" data-sort-order="desc" data-pagination-successively-size="3"
                            data-query-params="queryParams">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="title" data-sortable="true">{{ __('Property') }}</th>
                                    <th scope="col" data-field="unit_code" data-sortable="false">{{ __('Unit Code') }}</th>
                                    <th scope="col" data-field="project_name" data-sortable="false">{{ __('Project') }}</th>
                                    <th scope="col" data-field="total_units" data-sortable="true">{{ __('Total') }}</th>
                                    <th scope="col" data-field="available_units" data-sortable="true">{{ __('Available') }}</th>
                                    <th scope="col" data-field="reserved_units" data-sortable="true">{{ __('Reserved') }}</th>
                                    <th scope="col" data-field="sold_units" data-sortable="true">{{ __('Sold') }}</th>
                                    <th scope="col" data-field="unit_status" data-sortable="true" data-formatter="statusFormatter">{{ __('Status') }}</th>
                                    @if (has_permissions('update', 'project_inventory') || has_permissions('read', 'project_inventory'))
                                        <th scope="col" data-field="operate" data-align="center" data-sortable="false">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="inventoryModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Adjust Inventory') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="inventoryForm">
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <input type="hidden" id="property_id" name="property_id">
                        <div class="alert alert-light border" id="inventoryCurrent"></div>
                        <div class="form-group">
                            <label for="delta_units">{{ __('Delta Units') }} <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="delta_units" name="delta_units" required step="1">
                            <small class="form-text text-muted">{{ __('Use positive to add available units, negative to subtract.') }}</small>
                        </div>
                        <div class="form-group">
                            <label for="notes">{{ __('Notes') }}</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Adjust') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        function queryParams(p) {
            return { sort: p.sort, order: p.order, offset: p.offset, limit: p.limit, search: p.search };
        }

        function statusFormatter(value, row, index) {
            return `<span class="badge bg-${row.status_color}">${row.status_label}</span>`;
        }

        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $('#property_id').val(row.id);
                $('#delta_units').val(0);
                $('#notes').val('');
                $('#inventoryCurrent').html(
                    `<strong>${row.title}</strong><br>Available: ${row.available_units} | Reserved: ${row.reserved_units} | Sold: ${row.sold_units}`
                );
                $('#inventoryModal').modal('show');
            }
        };

        $('#inventoryForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: "{{ route('admin.project-inventory.adjust') }}",
                type: "POST",
                data: $(this).serialize(),
                success: function(response) {
                    if (response.error === false) {
                        Toastify({ text: response.message, duration: 4000, close: true, backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)" }).showToast();
                        $('#inventoryModal').modal('hide');
                        $('#table_list').bootstrapTable('refresh');
                    } else {
                        Toastify({ text: response.message, duration: 6000, close: true, backgroundColor: '#dc3545' }).showToast();
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Something went wrong';
                    Toastify({ text: msg, duration: 6000, close: true, backgroundColor: '#dc3545' }).showToast();
                }
            });
        });
    </script>
@endsection
