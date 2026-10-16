@extends('layouts.app')

@section('title', 'Edit ' . $employee->full_name . ' · ZamPayroll')

@section('content')
    @include('dashboard.employees._journey', ['mode' => 'edit'])
@endsection
