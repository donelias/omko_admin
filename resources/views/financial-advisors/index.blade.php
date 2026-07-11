@extends('layouts.main')

@section('title')
    {{ __('Financial Advisors') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"></div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card add-category mt-3">
            <div class="card-header">
                <div class="divider">
                    <div class="divider-text"><h4>{{ __('Add Financial Advisor') }}</h4></div>
                </div>
            </div>
            <div class="card-content">
                <div class="card-body">
                    {!! Form::open(['url' => route('financial-advisors.store'), 'data-parsley-validate', 'class' => 'create-form']) !!}
                    <div class="row">
                        <div class="col-md-4 form-group mandatory">
                            {{ Form::label('entity_type', __('Entity Type'), ['class' => 'form-label']) }}
                            <select name="entity_type" class="form-select" data-parsley-required="true" id="entity-type-select">
                                <option value="">{{ __('Select Entity Type') }}</option>
                                <option value="App\Models\Bank">{{ __('Bank') }}</option>
                                <option value="App\Models\Cooperative">{{ __('Cooperative') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group mandatory" id="entity-select-group">
                            {{ Form::label('entity_id', __('Entity'), ['class' => 'form-label']) }}
                            <select name="entity_id" class="form-select" data-parsley-required="true" id="entity-select">
                                <option value="">{{ __('Select') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            {{ Form::label('is_primary', __('Primary Advisor'), ['class' => 'form-label']) }}
                            <div class="form-check mt-2">
                                {{ Form::checkbox('is_primary', 1, false, ['class' => 'form-check-input', 'id' => 'is_primary']) }}
                                {{ Form::label('is_primary', __('Set as primary advisor'), ['class' => 'form-check-label']) }}
                            </div>
                        </div>
                        <div class="col-md-6 form-group mandatory">
                            {{ Form::label('name', __('Advisor Name'), ['class' => 'form-label']) }}
                            {{ Form::text('name', '', ['class' => 'form-control', 'placeholder' => __('Enter Advisor Name'), 'data-parsley-required' => 'true']) }}
                        </div>
                        <div class="col-md-6 form-group mandatory">
                            {{ Form::label('email', __('Advisor Email'), ['class' => 'form-label']) }}
                            {{ Form::email('email', '', ['class' => 'form-control', 'placeholder' => __('Enter Advisor Email'), 'data-parsley-required' => 'true']) }}
                        </div>
                        <div class="col-md-6 form-group">
                            {{ Form::label('phone', __('Phone'), ['class' => 'form-label']) }}
                            {{ Form::text('phone', '', ['class' => 'form-control', 'placeholder' => __('Enter Phone')]) }}
                        </div>
                        <div class="col-sm-12 text-end" style="margin-top:2%;">
                            {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
                        </div>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <div class="btn-group" role="group" id="entity-type-filter">
                        <button type="button" class="btn btn-outline-primary active" data-value="">{{ __('All') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="App\Models\Bank">{{ __('Banks') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-value="App\Models\Cooperative">{{ __('Cooperatives') }}</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped" id="table_list" data-toggle="table"
                            data-url="{{ route('financial-advisors.show', 1) }}" data-click-to-select="true" data-responsive="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5,10,20,50,100,200]"
                            data-search="true" data-show-columns="true" data-show-refresh="true"
                            data-trim-on-search="false" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams"
                            data-response-handler="globalTableResponseHandler">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="no" data-sortable="false" data-width="5%">{{ __('No') }}</th>
                                    <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                    <th scope="col" data-field="email" data-sortable="true">{{ __('Email') }}</th>
                                    <th scope="col" data-field="phone" data-sortable="true">{{ __('Phone') }}</th>
                                    <th scope="col" data-field="entity_type_label" data-sortable="false" data-align="center">{{ __('Type') }}</th>
                                    <th scope="col" data-field="entity_name" data-sortable="false">{{ __('Entity') }}</th>
                                    <th scope="col" data-field="is_primary" data-sortable="false" data-align="center" data-formatter="booleanFormatter">{{ __('Primary') }}</th>
                                    @if (has_permissions('update', 'financial_advisor'))
                                        <th scope="col" data-field="is_active" data-sortable="false" data-align="center" data-formatter="enableDisableSwitchFormatter">{{ __('Active') }}</th>
                                    @else
                                        <th scope="col" data-field="is_active" data-sortable="false" data-align="center" data-formatter="yesNoStatusFormatter">{{ __('Active') }}</th>
                                    @endif
                                    @if (has_permissions('update', 'financial_advisor') || has_permissions('delete', 'financial_advisor'))
                                        <th scope="col" data-field="operate" data-sortable="false" data-align="center" data-events="actionEvents">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="editModalLabel">{{ __('Edit Financial Advisor') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal edit-form" action="{{ url('financial-advisors') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" id="edit-id" name="edit_id">
                        <div class="row">
                            <div class="col-md-6 form-group mandatory">
                                {{ Form::label('edit_name', __('Advisor Name'), ['class' => 'form-label']) }}
                                {{ Form::text('edit_name', '', ['class' => 'form-control', 'id' => 'edit-name', 'required' => true]) }}
                            </div>
                            <div class="col-md-6 form-group mandatory">
                                {{ Form::label('edit_email', __('Advisor Email'), ['class' => 'form-label']) }}
                                {{ Form::email('edit_email', '', ['class' => 'form-control', 'id' => 'edit-email', 'required' => true]) }}
                            </div>
                            <div class="col-md-6 form-group">
                                {{ Form::label('edit_phone', __('Phone'), ['class' => 'form-label']) }}
                                {{ Form::text('edit_phone', '', ['class' => 'form-control', 'id' => 'edit-phone']) }}
                            </div>
                            <div class="col-md-6 form-group">
                                {{ Form::label('edit_is_primary', __('Primary Advisor'), ['class' => 'form-label']) }}
                                <div class="form-check mt-2">
                                    {{ Form::checkbox('edit_is_primary', 1, false, ['class' => 'form-check-input', 'id' => 'edit-is-primary']) }}
                                    {{ Form::label('edit_is_primary', __('Set as primary advisor'), ['class' => 'form-check-label']) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary" id="btn_submit">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        var banks = @json($banks ?? []);
        var cooperatives = @json($cooperatives ?? []);

        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
                entity_type: $('#entity-type-filter button.active').data('value')
            };
        }

        $('#entity-type-filter button').on('click', function() {
            $('#entity-type-filter button').removeClass('active');
            $(this).addClass('active');
            $('#table_list').bootstrapTable('refresh');
        });

        $('#entity-type-select').on('change', function() {
            var type = $(this).val();
            var $select = $('#entity-select');
            $select.empty().append('<option value="">{{ __('Select') }}</option>');
            var data = [];
            if (type === 'App\\Models\\Bank') {
                data = banks;
            } else if (type === 'App\\Models\\Cooperative') {
                data = cooperatives;
            }
            $.each(data, function(i, item) {
                $select.append($('<option>', { value: item.id, text: item.name + (item.interest_rate ? ' (' + item.interest_rate + '%)' : '') }));
            });
        });

        window.actionEvents = {
            'click .edit_btn': function(e, value, row) {
                $('#edit-id').val(row.id);
                $('#edit-name').val(row.name);
                $('#edit-email').val(row.email);
                $('#edit-phone').val(row.phone);
                $('#edit-is-primary').prop('checked', row.is_primary == 1 || row.is_primary == true);
            }
        };

        function booleanFormatter(value, row, index) {
            if (value == 1 || value == true) return '<span class="badge bg-success">{{ __('Yes') }}</span>';
            return '<span class="badge bg-secondary">{{ __('No') }}</span>';
        }
    </script>
@endsection
