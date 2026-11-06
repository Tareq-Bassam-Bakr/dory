@extends('guest.layout')

@section('title', $title)

@section('content')
    <section class="card">
        <h1>{{ $title }}</h1>
        <p class="muted">{{ $body }}</p>
        @if (! empty($retryUrl))
            <a class="button" href="{{ $retryUrl }}">{{ __('guest.retry') }}</a>
        @endif
    </section>
@endsection
