@extends('layouts.main')

@section('title')
    {{ __('Assign Package') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
        </div>
    </div>
@endsection

@section('content')
<section class="section">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <form method="POST" action="{{ route('assign-package.store') }}" class="create-form" data-success-function="formSuccessFunction">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="customer_id" class="form-label">{{ __('Select User') }}</label>
                                        <select id="customer_id" name="customer_id" class="form-control select2-ajax" style="width: 100%" required></select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="package_id" class="form-label">{{ __('Select Package') }}</label>
                                        <select id="package_id" name="package_id" class="form-control select2-ajax" style="width: 100%" required></select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">{{ __('Assign') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</section>
<section class="section">
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-12">
                    <table class="table-light" aria-describedby="mydesc" class='table-striped' id="table_list"
                        data-toggle="table" data-url="{{ route('payment.list') }}" data-click-to-select="true"
                        data-side-pagination="server" data-pagination="true"
                        data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-search-align="right"
                        data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                        data-trim-on-search="false" data-responsive="true" data-sort-name="id" data-sort-order="desc"
                        data-pagination-successively-size="3" data-query-params="queryParams">
                        <thead>
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true"> {{ __('ID') }}</th>
                                <th scope="col" data-field="customer.name" data-align="center" data-sortable="false"> {{ __('Client Name') }}</th>
                                <th scope="col" data-field="package.name" data-align="center" data-sortable="false"> {{ __('Package Name') }} </th>
                                <th scope="col" data-field="amount" data-align="center" data-sortable="true" data-formatter="paymentAmountFormatter"> {{ __('Amount') }} </th>
                                <th scope="col" data-field="payment_type" data-align="center" data-sortable="true">{{ __('Payment Type') }} </th>
                                <th scope="col" data-field="transaction_id" data-align="center" data-sortable="true">{{ __('Transaction Id') }} </th>
                                <th scope="col" data-field="payment_gateway" data-align="center" data-sortable="true">{{ __('Payment Gateway') }} </th>
                                <th scope="col" data-field="payment_status" data-align="center" data-sortable="true" data-formatter="paymentStatusFormatter"> {{ __('Status') }}</th>
                                <th scope="col" data-field="created_at" data-align="center" data-sortable="true" data-visible="false"> {{ __('Payment Date') }} </th>
                                <th scope="col" data-field="updated_at" data-align="center" data-sortable="true" data-visible="false"> {{ __('Payment Update Date') }} </th>
                                <th scope="col" data-field="operate" data-align="center" data-sortable="false"> {{ __('Action') }}</th>
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
        $(document).ready(function() {
            function initSelect2(selector, url, placeholder){
                $(selector).select2({
                    placeholder: placeholder,
                    allowClear: true,
                    width: 'resolve',
                    theme: 'bootstrap-5',
                    ajax: {
                        url: url,
                        dataType: 'json',
                        delay: 250,
                        cache: true,
                        data: function(params){
                            return {
                                q: params.term || '',
                                page: params.page || 1,
                                per_page: 20
                            };
                        },
                        processResults: function (data, params) {
                            params.page = params.page || 1;
                            return {
                                results: data.results || [],
                                pagination: {
                                    more: data.pagination && data.pagination.more ? true : false
                                }
                            };
                        }
                    },
                    minimumInputLength: 0
                });
            }

            initSelect2('#customer_id', '{{ route('select2.customers') }}', '{{ __('Search users...') }}');
            initSelect2('#package_id', '{{ route('select2.packages') }}', '{{ __('Search packages...') }}');
        });

        function formSuccessFunction(response) {
            if(!response.error){
                if (response && response.warning && response.data && response.data.confirm_required) {
                    Swal.fire({
                        title: window.trans ? window.trans['Are you sure'] : 'Are you sure',
                        text: window.trans ? window.trans['Selected user already has an active package. Do you want to assign anyway?'] : 'Selected user already has an active package. Do you want to assign anyway?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#198754',
                        cancelButtonColor: '#d33',
                        confirmButtonText: window.trans ? window.trans['Yes'] : 'Yes',
                        cancelButtonText: window.trans ? window.trans['No'] : 'No',
                        reverseButtons: true,
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const form = document.querySelector('.create-form');
                            if (form) {
                                // append / set force_assign flag and resubmit
                                let forceInput = form.querySelector('input[name="force_assign"]');
                                if (!forceInput) {
                                    forceInput = document.createElement('input');
                                    forceInput.type = 'hidden';
                                    forceInput.name = 'force_assign';
                                    form.appendChild(forceInput);
                                }
                                forceInput.value = '1';
                                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                            }
                        }
                    });
                    return;
                }else{
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            }
        }
        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,
                manual_payment_type_only: 1,
            };
        }
    </script>
@endsection