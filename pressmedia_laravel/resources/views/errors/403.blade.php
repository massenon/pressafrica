@extends('errors.layout')
@section('code', '403')
@section('title', \App\Support\Seo::byUrl('Accès refusé', 'Access denied'))
@section('text', \App\Support\Seo::byUrl('Vous n’avez pas les droits pour afficher cette page.', 'You are not allowed to see this page.'))

