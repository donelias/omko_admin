@extends('layouts.main')

@section('title')
    {{ __('Vacation Availability') }}
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
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Availability') }}</li>
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
                <h4>{{ __('Add Availability Range') }}</h4>
            </div>
            <div class="card-body">
                <form id="availabilityForm">
                    {{ csrf_field() }}
                    <div class="row">
                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label for="property_id">{{ __('Property') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="property_id" name="property_id" required>
                                    <option value="">{{ __('Select Property') }}</option>
                                    @foreach ($properties as $property)
                                        <option value="{{ $property->id }}">{{ $property->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-2">
                            <div class="form-group">
                                <label for="date_from">{{ __('Date From') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_from" name="date_from" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-2">
                            <div class="form-group">
                                <label for="date_to">{{ __('Date To') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_to" name="date_to" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-2">
                            <div class="form-group">
                                <label for="status">{{ __('Status') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="1">{{ __('Available') }}</option>
                                    <option value="0">{{ __('Blocked') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-2">
                            <div class="form-group">
                                <label for="nightly_price">{{ __('Nightly Price') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="nightly_price" name="nightly_price">
                            </div>
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary me-1 mb-1">{{ __('Save') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped" id="table_list"
                            data-toggle="table" data-url="{{ route('admin.short-term.availability.list') }}"
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
                                    <th scope="col" data-field="date_from" data-sortable="true">{{ __('Date From') }}</th>
                                    <th scope="col" data-field="date_to" data-sortable="true">{{ __('Date To') }}</th>
                                    <th scope="col" data-field="status" data-sortable="false" data-formatter="statusFormatter">{{ __('Status') }}</th>
                                    <th scope="col" data-field="nightly_price" data-sortable="false">{{ __('Nightly Price') }}</th>
                                    @if (has_permissions('delete', 'short_term_availability'))
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
@endsection

@section('script')
    <script>
        function queryParams(p) {
            return { sort: p.sort, order: p.order, offset: p.offset, limit: p.limit, search: p.search };
        }

        function statusFormatter(value, row, index) {
            const badges = { 'Disponible': 'success', 'Bloqueado': 'danger' };
            return `<span class="badge bg-${badges[value] || 'info'}">${value}</span>`;
        }

        $('#availabilityForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: "{{ route('admin.short-term.availability.store') }}",
                type: "POST",
                data: $(this).serialize(),
                success: function(response) {
                    if (response.error === false) {
                        Toastify({ text: response.message, duration: 4000, close: true, backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)" }).showToast();
                        $('#availabilityForm')[0].reset();
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
