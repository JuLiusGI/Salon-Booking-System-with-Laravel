@extends('errors.layout')
@section('code', '419')
@section('title', 'Your session expired')
@section('message', 'You were away long enough that we signed you out for safety. Sign in again and your work is still there.')
@section('extra')
    <a class="secondary" href="{{ route('login') }}">Sign in</a>
@endsection
