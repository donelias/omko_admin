@extends('layouts.main')

@section('title')
    {{ __('Vacation Reservations') }}
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
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Vacations') }}</li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Reservations') }}</li>
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
                            data-toggle="table" data-url="{{ route('admin.short-term.reservations.list') }}"
                            data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                            data-search-align="right" data-toolbar="#toolbar" data-show-columns="true"
                            data-show-refresh="true" data-trim-on-search="false" data-responsive="true"
                            data-sort-name="id" data-sort-order="desc" data-pagination-successively-size="3"
                            data-query-params="queryParams">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="property_title" data-sortable="false">{{ __('Property') }}</th>
                                    <th scope="col" data-field="customer_name" data-sortable="false">{{ __('Customer') }}</th>
                                    <th scope="col" data-field="check_in" data-sortable="true">{{ __('Check In') }}</th>
                                    <th scope="col" data-field="check_out" data-sortable="true">{{ __('Check Out') }}</th>
                                    <th scope="col" data-field="nights" data-sortable="false">{{ __('Nights') }}</th>
                                    <th scope="col" data-field="guests" data-sortable="false">{{ __('Guests') }}</th>
                                    <th scope="col" data-field="total_price" data-sortable="false">{{ __('Total') }}</th>
                                    <th scope="col" data-field="status" data-sortable="true" data-formatter="statusFormatter">{{ __('Status') }}</th>
                                    @if (has_permissions('update', 'short_term_reservations') || has_permissions('delete', 'short_term_reservations'))
                                        <th scope="col" data-field="operate" data-align="center" data-sortable="false" data-events="actionEvents">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="reservationModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Update Reservation Status') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="reservationForm">
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <input type="hidden" id="reservation_id" name="id">
                        <div class="form-group">
                            <label for="status_select">{{ __('Status') }}</label>
                            <select class="form-control" id="status_select" name="status" required>
                                <option value="pending">{{ __('Pending') }}</option>
                                <option value="confirmed">{{ __('Confirmed') }}</option>
                                <option value="cancelled">{{ __('Cancelled') }}</option>
                                <option value="completed">{{ __('Completed') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Update Status') }}</button>
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
            const badges = { pending: 'warning', confirmed: 'success', cancelled: 'danger', completed: 'secondary' };
            return `<span class="badge bg-${badges[value] || 'info'}">${value.toUpperCase()}</span>`;
        }

        window.actionEvents = {
            'click .edit_btn': function(e, value, row, index) {
                $('#reservation_id').val(row.id);
                $('#status_select').val(row.status).trigger('change');
                $('#reservationModal').modal('show');
            }
        };

        $('#reservationForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: "{{ route('admin.short-term.reservations.update-status') }}",
                type: "POST",
                data: $(this).serialize(),
                success: function(response) {
                    if (response.error === false) {
                        Toastify({ text: response.message, duration: 4000, close: true, backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)" }).showToast();
                        $('#reservationModal').modal('hide');
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
