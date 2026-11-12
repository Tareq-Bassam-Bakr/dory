@extends('admin.layout')
@use('App\Support\AdminFormat')

@php
    $statusKey = $payment->awaitsApproval() ? 'awaiting' : $payment->status->value;
    $sourceKey = $payment->provider === 'manual' ? 'manual' : ($payment->provider === 'admin' ? 'admin' : 'gateway');
    $title = $payment->number ?? __('admin.payments.record_title');
@endphp

@section('title', $title)

@section('content')
    <div class="page-header no-print">
        <div>
            <div class="breadcrumb"><a href="{{ route('admin.payments.index') }}">{{ __('admin.nav.payments') }}</a></div>
            <h1 class="ltr">{{ $title }}</h1>
            <p>
                @include('admin.partials.badge', ['tone' => $statusKey === 'awaiting' ? 'warning' : AdminFormat::tone($statusKey), 'label' => __('admin.payments.statuses.'.$statusKey)])
                @include('admin.partials.badge', ['tone' => $payment->isGatewayVerified() ? 'success' : 'info', 'label' => __('admin.payments.sources.'.$sourceKey)])
            </p>
        </div>
        <div class="actions">
            @if ($payment->status->value === 'paid')
                <button type="button" class="btn btn-secondary" data-print>@include('admin.partials.icon', ['name' => 'printer']) {{ __('admin.payments.print') }}</button>
            @endif
        </div>
    </div>

    @if ($payment->awaitsApproval())
        <div class="alert warning no-print">
            @include('admin.partials.icon', ['name' => 'clock'])
            <div>
                {{ __('admin.payments.awaiting_note') }}
                @if ($fourEyes && $isRecorder)
                    <br><strong>{{ __('admin.payments.four_eyes_yours') }}</strong>
                @endif
            </div>
        </div>
    @endif

    <div class="grid grid-main">
        <div class="stack">
            @if ($payment->status->value === 'paid' && $payment->number)
                <div class="card invoice">
                    <div class="card-header">
                        <div>
                            <h2>{{ __('admin.payments.invoice') }} <span class="ltr">{{ $payment->number }}</span></h2>
                            {{-- Issued when it was numbered: on approval, or when the provider confirmed it. --}}
                            <p>{{ __('admin.payments.issued', ['date' => AdminFormat::date($payment->reviewed_at ?? $payment->paid_at)]) }}</p>
                        </div>
                        <img src="{{ asset('dory-admin/dory-icon.png') }}" alt="DORY" width="48" height="48">
                    </div>
                    <div class="parties">
                        <div>
                            <div class="subtle">{{ __('admin.payments.from') }}</div>
                            <strong>{{ $seller }}</strong>
                        </div>
                        <div>
                            <div class="subtle">{{ __('admin.payments.billed_to') }}</div>
                            <strong>{{ $payment->business?->name }}</strong>
                            @if ($payment->business?->email)
                                <div class="ltr">{{ $payment->business->email }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>{{ __('admin.payments.item') }}</th><th>{{ __('admin.payments.service_period') }}</th><th class="num">{{ __('admin.common.amount') }}</th></tr></thead>
                            <tbody>
                            <tr>
                                <td>{{ __('admin.payments.item_line', ['plan' => $payment->plan?->name(app()->getLocale()), 'period' => __('admin.periods.'.$payment->billing_period->value)]) }}</td>
                                <td class="nowrap">{{ AdminFormat::date($payment->period_start) }} – {{ AdminFormat::date($payment->period_end) }}</td>
                                <td class="num ltr nowrap">{{ AdminFormat::money($payment->amount_minor, $payment->currency) }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="actions">
                        <span class="subtle">{{ __('admin.payments.total') }}</span>
                        <span class="total ltr">{{ AdminFormat::money($payment->amount_minor, $payment->currency) }}</span>
                    </div>
                    <p class="stamp">
                        {{ __('admin.payments.paid_by', ['method' => $payment->payment_method ? __('admin.methods.'.$payment->payment_method->value) : '—', 'date' => AdminFormat::date($payment->paid_at)]) }}
                        @if ($payment->provider_reference) · {{ __('admin.payments.reference') }}: <span class="ltr">{{ $payment->provider_reference }}</span>@endif
                        <br>{{ $payment->isGatewayVerified() ? __('admin.payments.stamp_gateway') : __('admin.payments.stamp_manual') }}
                        <br>{{ __('admin.payments.legal_note') }}
                    </p>
                </div>
            @endif

            <div class="card no-print">
                <div class="card-header"><h2>{{ __('admin.payments.details') }}</h2></div>
                <dl class="kv">
                    <dt>{{ __('admin.common.business') }}</dt>
                    <dd>
                        @if ($payment->business)
                            <a href="{{ route('admin.businesses.show', $payment->business) }}">{{ $payment->business->name }}</a>
                        @else
                            —
                        @endif
                    </dd>
                    <dt>{{ __('admin.common.plan') }}</dt><dd>{{ $payment->plan?->name(app()->getLocale()) }} · {{ __('admin.periods.'.$payment->billing_period->value) }}</dd>
                    <dt>{{ __('admin.common.amount') }}</dt><dd class="ltr">{{ AdminFormat::money($payment->amount_minor, $payment->currency) }}</dd>
                    <dt>{{ __('admin.payments.source') }}</dt><dd>{{ __('admin.payments.sources.'.$sourceKey) }} <span class="sub">{{ __('admin.payments.sources_help.'.$sourceKey) }}</span></dd>
                    <dt>{{ __('admin.payments.method') }}</dt><dd>{{ $payment->payment_method ? __('admin.methods.'.$payment->payment_method->value) : '—' }}</dd>
                    <dt>{{ __('admin.payments.reference') }}</dt><dd class="ltr">{{ $payment->provider_reference ?? '—' }}</dd>
                    @if ($payment->received_at)
                        <dt>{{ __('admin.payments.received_on') }}</dt><dd>{{ AdminFormat::date($payment->received_at) }}</dd>
                    @endif
                    @if ($payment->period_start)
                        <dt>{{ __('admin.payments.service_period') }}</dt><dd>{{ AdminFormat::date($payment->period_start) }} – {{ AdminFormat::date($payment->period_end) }}</dd>
                    @endif
                    @if ($payment->failure_reason)
                        <dt>{{ $payment->status->value === 'void' ? __('admin.payments.rejection_reason') : __('admin.payments.failure_reason') }}</dt><dd>{{ $payment->failure_reason }}</dd>
                    @endif
                    <dt>{{ __('admin.common.notes') }}</dt><dd>{{ $payment->notes ?: '—' }}</dd>
                    <dt>{{ __('admin.common.id') }}</dt><dd class="mono ltr">{{ $payment->public_id }}</dd>
                </dl>
            </div>
        </div>

        <div class="stack no-print">
            @if ($payment->awaitsApproval())
                <form class="card" method="post" action="{{ route('admin.payments.approve', $payment) }}" data-confirm="{{ __('admin.payments.approve_confirm') }}">
                    @csrf
                    <h2>{{ __('admin.payments.approve_title') }}</h2>
                    <p class="subtle section-gap">{{ __('admin.payments.approve_help') }}</p>
                    <button type="submit" class="btn btn-success" @disabled($fourEyes && $isRecorder)>@include('admin.partials.icon', ['name' => 'check']) {{ __('admin.payments.approve') }}</button>
                </form>
                <form class="card" method="post" action="{{ route('admin.payments.reject', $payment) }}" data-confirm="{{ __('admin.payments.reject_confirm') }}">
                    @csrf
                    <h2>{{ __('admin.payments.reject_title') }}</h2>
                    <div class="field section-gap">
                        <label for="reject_reason">{{ __('admin.common.reason') }}</label>
                        <textarea id="reject_reason" name="reason" maxlength="120" required class="@error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                        @error('reason')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="btn btn-danger">{{ __('admin.payments.reject') }}</button>
                </form>
            @endif

            <div class="card">
                <h2>{{ __('admin.payments.timeline') }}</h2>
                <ol class="timeline section-gap">
                    <li>
                        {{ $payment->provider === 'manual' ? __('admin.payments.events.recorded') : __('admin.payments.events.issued') }}
                        <span class="sub">{{ AdminFormat::dateTime($payment->issued_at) }}@if ($payment->recorder) · {{ $payment->recorder->name }}@endif</span>
                    </li>
                    @if ($payment->status->value === 'paid')
                        <li class="success">
                            {{ $payment->provider === 'manual' ? __('admin.payments.events.approved') : __('admin.payments.events.paid') }}
                            <span class="sub">{{ AdminFormat::dateTime($payment->reviewed_at ?? $payment->paid_at) }}@if ($payment->reviewer) · {{ $payment->reviewer->name }}@endif</span>
                        </li>
                    @elseif ($payment->status->value === 'void')
                        <li class="danger">
                            {{ __('admin.payments.events.rejected') }}
                            <span class="sub">{{ AdminFormat::dateTime($payment->reviewed_at) }}@if ($payment->reviewer) · {{ $payment->reviewer->name }}@endif</span>
                        </li>
                    @elseif ($payment->status->value === 'failed')
                        <li class="danger">
                            {{ __('admin.payments.events.failed') }}
                            <span class="sub">{{ AdminFormat::dateTime($payment->failed_at) }}</span>
                        </li>
                    @else
                        <li class="warning">{{ __('admin.payments.events.waiting') }}</li>
                    @endif
                </ol>
            </div>

            <div class="card">
                <h2>{{ __('admin.payments.history') }}</h2>
                <ul class="list section-gap">
                    @forelse ($activity as $entry)
                        <li>
                            <span>{{ __('admin.audit.actions.'.$entry->action->value) }} <span class="sub">{{ $entry->actor?->name ?? __('admin.audit.console') }}</span></span>
                            <span class="subtle nowrap">{{ AdminFormat::dateTime($entry->created_at) }}</span>
                        </li>
                    @empty
                        <li class="subtle">{{ __('admin.common.empty') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
