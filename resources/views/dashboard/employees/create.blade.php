@extends('layouts.app')

@section('title', 'Add employee · ZamPayroll')

@section('content')
    @include('dashboard.employees._journey', ['mode' => 'create'])
@endsection
