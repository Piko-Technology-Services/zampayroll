@extends('layouts.app')

@section('content')

<h4 class="mb-3"><i class="bi bi-bar-chart"></i> Reports</h4>

<div class="panel p-3">

{{-- BETA BANNER --}}
    <div class="alert alert-primary mb-3" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Beta Mode - Under development</strong> (Data maybe lost if used, use only for testing purposes)
    </div>

    <div class="row g-3">

        <div class="col-md-4 mouse-pointer" onclick="window.location='{{ route('payroll.reports.index') }}'" style="cursor: pointer;">
            <div class="border rounded p-3">
                <i class="bi bi-calendar-check"></i>
                <h6>Payroll Reports</h6>
            </div>
        </div>

        <!-- <a href="" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Payroll reports
        </a> -->

    </div>

</div>

@endsection