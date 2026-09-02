@extends('errors.layout')
@section('code', '404')
@section('title', 'We could not find that page')
@section('message', 'The link may be out of date, or the page may have moved. Nothing has gone wrong with your account.')
@section('extra')
    <a class="secondary" href="{{ url('/services') }}">Browse services</a>
@endsection
