@extends('errors.layout')
@section('code', '503')
@section('title', \App\Support\Seo::byUrl('Site en maintenance', 'Site under maintenance'))
{{-- Message from Settings > maintenance when the database answers; a fixed text otherwise. --}}
@section('text', rescue(fn () => \App\Support\Settings::get('maintenanceMsg'), null, false) ?: \App\Support\Seo::byUrl('Le site revient très vite.', 'The site will be back shortly.'))
