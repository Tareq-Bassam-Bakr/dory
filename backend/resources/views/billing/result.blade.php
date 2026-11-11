@extends('billing.layout')

@php
    $badge = match ($status) {
        'paid' => 'ok',
        'confirming', 'pending' => 'wait',
        default => 'bad',
    };
    $known = in_array($status, ['paid', 'confirming', 'pending', 'failed', 'expired', 'unavailable'], true) ? $status : 'failed';
@endphp

@section('title', __('billing.result.'.$known.'.title'))

@section('content')
    <section class="card">
        <span class="badge {{ $badge }}">{{ __('billing.result.'.$known.'.badge') }}</span>
        <h1>{{ __('billing.result.'.$known.'.title') }}</h1>
        <p class="muted">{{ __('billing.result.'.$known.'.body') }}</p>
        <div class="row"><span>{{ __('billing.plan') }}</span><strong>{{ $checkout->plan->name(app()->getLocale()) }}</strong></div>
        <div class="row"><span>{{ __('billing.period') }}</span><strong>{{ __('billing.periods.'.$checkout->billing_period->value) }}</strong></div>
        <div class="row"><span>{{ __('billing.amount') }}</span><strong class="amount">{{ $amount }} {{ $checkout->currency }}</strong></div>
        @if (in_array($known, ['confirming', 'pending'], true))
            <a class="button" href="">{{ __('billing.check_again') }}</a>
        @endif
        <p class="muted">{{ __('billing.back_to_app') }}</p>
    </section>
@endsection
