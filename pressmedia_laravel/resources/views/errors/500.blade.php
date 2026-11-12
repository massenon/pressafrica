@extends('errors.layout')
@section('code', '500')
@section('title', \App\Support\Seo::byUrl('Erreur du serveur', 'Server error'))
@section('text', \App\Support\Seo::byUrl('Un incident technique est survenu. La rédaction en est informée ; réessayez dans quelques instants.', 'Something went wrong on our side. Please try again in a moment.'))

