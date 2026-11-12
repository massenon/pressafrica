@extends('site.layout')
@section('main')
<div class="container-page"><section class="soon" aria-live="polite"><div><h1 class="page-title">{{ $message }}</h1><p><a class="btn btn-primary" href="{{ url(app()->getLocale()) }}">Africa Global Press</a></p></div></section></div>
@endsection
