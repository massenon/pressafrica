@extends('errors.layout')
@section('code', '429')
@section('title', \App\Support\Seo::byUrl('Trop de requêtes', 'Too many requests'))
@section('text', \App\Support\Seo::byUrl('Merci de patienter une minute avant de réessayer.', 'Please wait a minute before trying again.'))

