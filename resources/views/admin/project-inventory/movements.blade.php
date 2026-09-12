@extends('layouts.main')

@section('title')
    {{ __('Inventory Movements') }}
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
                        <li class="breadcrumb-item"><a href="{{ route('admin.project-inventory.index') }}">{{ __('On-Plan Inventory') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Movements') }}</li>
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
                <h4 class="mb-3">{{ $property->title }} <small class="text-muted">#{{ $property->id }} — {{ $property->unit_code ?? '' }}</small></h4>
                <div class="row mb-3">
                    <div class="col-6 col-md-3">
                        <div class="alert alert-success mb-0">
                            <strong>{{ __('Available') }}:</strong> {{ (int) $property->available_units }}
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="alert alert-warning mb-0">
                            <strong>{{ __('Reserved') }}:</strong> {{ (int) $property->reserved_units }}
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="alert alert-secondary mb-0">
                            <strong>{{ __('Sold') }}:</strong> {{ (int) $property->sold_units }}
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="alert alert-info mb-0">
                            <strong>{{ __('Total') }}:</strong> {{ (int) $property->total_units }}
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped">
                            <thead class="thead-dark">
                                <tr>
                                    <th>{{ __('Event') }}</th>
                                    <th>{{ __('Delta') }}</th>
                                    <th>{{ __('Before') }}</th>
                                    <th>{{ __('After') }}</th>
                                    <th>{{ __('Notes') }}</th>
                                    <th>{{ __('Created At') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($movements as $movement)
                                    <tr>
                                        <td><span class="badge bg-{{ $movement['event_color'] }}">{{ $movement['event_type'] }}</span></td>
                                        <td>{{ $movement['delta_units'] > 0 ? '+' : '' }}{{ $movement['delta_units'] }}</td>
                                        <td>{{ $movement['before_units'] }}</td>
                                        <td>{{ $movement['after_units'] }}</td>
                                        <td>{{ $movement['notes'] ?: '-' }}</td>
                                        <td>{{ $movement['created_at'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center">{{ __('No movements found') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <a href="{{ route('admin.project-inventory.index') }}" class="btn btn-secondary mt-2"><i class="bi bi-arrow-left"></i> {{ __('Back') }}</a>
            </div>
        </div>
    </section>
@endsection
