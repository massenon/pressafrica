{{-- Fixed page exported from the prototype (resources/chrome/page-*.html). --}}
@extends('site.layout')
@push('jsonld')
{!! $jsonld !!}
@endpush
@section('main')
{!! $html !!}
@endsection
