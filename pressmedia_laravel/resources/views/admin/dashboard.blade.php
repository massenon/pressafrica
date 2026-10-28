@extends('admin.layout')
@section('title', 'Dashboard')
@section('content')
<h1 class="text-2xl font-bold">Dashboard</h1>
<p class="mt-2 text-slate-600">Signed in as {{ auth()->user()->email }}.</p>
@endsection
