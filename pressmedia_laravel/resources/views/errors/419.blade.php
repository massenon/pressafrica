@extends('errors.layout')
@section('code', '419')
@section('title', \App\Support\Seo::byUrl('Session expirée', 'Session expired'))
@section('text', \App\Support\Seo::byUrl('La page est restée ouverte trop longtemps. Rechargez-la puis recommencez.', 'The page was open for too long. Reload it and try again.'))

