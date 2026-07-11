@extends('layouts.main')

@section('title')
    {{ __('Cooperatives') }}
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
                    <div class="divider-text"><h4>{{ __('Add Cooperative') }}</h4></div>
                </div>
            </div>
            <div class="card-content">
                <div class="card-body">
                    {!! Form::open(['url' => route('cooperatives.store'), 'data-parsley-validate', 'class' => 'create-form']) !!}
                    <div class="row">
                        <div class="col-md-6 form-group mandatory">
                            {{ Form::label('name', __('Cooperative Name'), ['class' => 'form-label']) }}
                            {{ Form::text('name', '', ['class' => 'form-control', 'placeholder' => __('Enter Cooperative Name'), 'data-parsley-required' => 'true']) }}
                        </div>
                        <div class="col-md-3 form-group">
                            {{ Form::label('interest_rate', __('Interest Rate (%)'), ['class' => 'form-label']) }}
                            {{ Form::number('interest_rate', '', ['class' => 'form-control', 'placeholder' => __('e.g. 12.00'), 'step' => '0.01', 'min' => '0', 'max' => '99.99']) }}
                        </div>
                        <div class="col-md-3 form-group mandatory">
                            {{ Form::label('currency', __('Currency'), ['class' => 'form-label']) }}
                            {{ Form::select('currency', ['DOP' => 'DOP', 'USD' => 'USD'], 'DOP', ['class' => 'form-select', 'data-parsley-required' => 'true']) }}
                        </div>
                        <div class="col-md-6 form-group">
                            {{ Form::label('email', __('Email'), ['class' => 'form-label']) }}
                            {{ Form::email('email', '', ['class' => 'form-control', 'placeholder' => __('Enter Email')]) }}
                        </div>
                        <div class="col-md-3 form-group">
                            {{ Form::label('phone', __('Phone'), ['class' => 'form-label']) }}
                            {{ Form::text('phone', '', ['class' => 'form-control', 'placeholder' => __('Enter Phone')]) }}
                        </div>
                        <div class="col-md-3 form-group">
                            {{ Form::label('sort_order', __('Sort Order'), ['class' => 'form-label']) }}
                            {{ Form::number('sort_order', 0, ['class' => 'form-control', 'min' => '0']) }}
                        </div>
                        <div class="col-md-12 form-group">
                            {{ Form::label('website', __('Website'), ['class' => 'form-label']) }}
                            {{ Form::url('website', '', ['class' => 'form-control', 'placeholder' => __('https://')]) }}
                        </div>
                        <div class="col-md-12 form-group">
                            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
                            {{ Form::textarea('description', '', ['class' => 'form-control', 'placeholder' => __('Optional description'), 'rows' => 2]) }}
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
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped" id="table_list" data-toggle="table"
                            data-url="{{ route('cooperatives.show', 1) }}" data-click-to-select="true" data-responsive="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5,10,20,50,100,200]"
                            data-search="true" data-show-columns="true" data-show-refresh="true"
                            data-trim-on-search="false" data-sort-name="sort_order" data-sort-order="asc"
                            data-pagination-successively-size="3" data-query-params="queryParams"
                            data-response-handler="globalTableResponseHandler">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="no" data-sortable="false" data-width="5%">{{ __('No') }}</th>
                                    <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                    <th scope="col" data-field="interest_rate" data-sortable="true" data-align="center">{{ __('Rate (%)') }}</th>
                                    <th scope="col" data-field="currency" data-sortable="true" data-align="center">{{ __('Currency') }}</th>
                                    <th scope="col" data-field="email" data-sortable="true">{{ __('Email') }}</th>
                                    <th scope="col" data-field="phone" data-sortable="true">{{ __('Phone') }}</th>
                                    <th scope="col" data-field="sort_order" data-sortable="true" data-align="center">{{ __('Sort') }}</th>
                                    @if (has_permissions('update', 'cooperative'))
                                        <th scope="col" data-field="is_active" data-sortable="false" data-align="center" data-formatter="enableDisableSwitchFormatter">{{ __('Active') }}</th>
                                    @else
                                        <th scope="col" data-field="is_active" data-sortable="false" data-align="center" data-formatter="yesNoStatusFormatter">{{ __('Active') }}</th>
                                    @endif
                                    @if (has_permissions('update', 'cooperative') || has_permissions('delete', 'cooperative'))
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
                    <h6 class="modal-title" id="editModalLabel">{{ __('Edit Cooperative') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal edit-form" action="{{ url('cooperatives') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" id="edit-id" name="edit_id">
                        <div class="row">
                            <div class="col-md-6 form-group mandatory">
                                {{ Form::label('edit_name', __('Cooperative Name'), ['class' => 'form-label']) }}
                                {{ Form::text('edit_name', '', ['class' => 'form-control', 'id' => 'edit-name', 'required' => true]) }}
                            </div>
                            <div class="col-md-3 form-group">
                                {{ Form::label('edit_interest_rate', __('Interest Rate (%)'), ['class' => 'form-label']) }}
                                {{ Form::number('edit_interest_rate', '', ['class' => 'form-control', 'id' => 'edit-interest-rate', 'step' => '0.01', 'min' => '0', 'max' => '99.99']) }}
                            </div>
                            <div class="col-md-3 form-group mandatory">
                                {{ Form::label('edit_currency', __('Currency'), ['class' => 'form-label']) }}
                                {{ Form::select('edit_currency', ['DOP' => 'DOP', 'USD' => 'USD'], null, ['class' => 'form-select', 'id' => 'edit-currency', 'required' => true]) }}
                            </div>
                            <div class="col-md-6 form-group">
                                {{ Form::label('edit_email', __('Email'), ['class' => 'form-label']) }}
                                {{ Form::email('edit_email', '', ['class' => 'form-control', 'id' => 'edit-email']) }}
                            </div>
                            <div class="col-md-3 form-group">
                                {{ Form::label('edit_phone', __('Phone'), ['class' => 'form-label']) }}
                                {{ Form::text('edit_phone', '', ['class' => 'form-control', 'id' => 'edit-phone']) }}
                            </div>
                            <div class="col-md-3 form-group">
                                {{ Form::label('edit_sort_order', __('Sort Order'), ['class' => 'form-label']) }}
                                {{ Form::number('edit_sort_order', 0, ['class' => 'form-control', 'id' => 'edit-sort-order', 'min' => '0']) }}
                            </div>
                            <div class="col-md-12 form-group">
                                {{ Form::label('edit_website', __('Website'), ['class' => 'form-label']) }}
                                {{ Form::url('edit_website', '', ['class' => 'form-control', 'id' => 'edit-website']) }}
                            </div>
                            <div class="col-md-12 form-group">
                                {{ Form::label('edit_description', __('Description'), ['class' => 'form-label']) }}
                                {{ Form::textarea('edit_description', '', ['class' => 'form-control', 'id' => 'edit-description', 'rows' => 2]) }}
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
        function queryParams(p) {
            return { sort: p.sort, order: p.order, offset: p.offset, limit: p.limit, search: p.search };
        }

        window.actionEvents = {
            'click .edit_btn': function(e, value, row) {
                $('#edit-id').val(row.id);
                $('#edit-name').val(row.name);
                $('#edit-interest-rate').val(row.interest_rate);
                $('#edit-currency').val(row.currency);
                $('#edit-email').val(row.email);
                $('#edit-phone').val(row.phone);
                $('#edit-website').val(row.website);
                $('#edit-description').val(row.description);
                $('#edit-sort-order').val(row.sort_order);
            }
        };
    </script>
@endsection
