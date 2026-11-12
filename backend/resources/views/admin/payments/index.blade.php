@extends('admin.layout')
@use('App\Support\AdminFormat')

@section('title', __('admin.payments.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('admin.payments.title') }}</h1>
            <p>{{ __('admin.payments.subtitle') }}</p>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('admin.payments.create') }}">@include('admin.partials.icon', ['name' => 'plus']) {{ __('admin.payments.record') }}</a>
        </div>
    </div>

    <div class="alert info">
        @include('admin.partials.icon', ['name' => 'info'])
        <div>
            {{ __('admin.payments.sources_note') }}
            @if ($fourEyes)
                <br>{{ __('admin.payments.four_eyes_on') }}
            @endif
        </div>
    </div>

    <nav class="tabs" aria-label="{{ __('admin.payments.title') }}">
        @foreach ($tabs as $key => $count)
            <a href="{{ route('admin.payments.index', ['tab' => $key]) }}" @if ($filters['tab'] === $key) aria-current="page" @endif>
                {{ __('admin.payments.tabs.'.$key) }}@if ($count !== null) ({{ $count }})@endif
            </a>
        @endforeach
    </nav>

    <div class="card">
        @if ($filters['tab'] === 'checkouts')
            <p class="subtle">{{ __('admin.payments.checkouts_note') }}</p>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('admin.common.business') }}</th>
                        <th>{{ __('admin.common.plan') }}</th>
                        <th class="num">{{ __('admin.common.amount') }}</th>
                        <th>{{ __('admin.payments.provider') }}</th>
                        <th>{{ __('admin.common.status') }}</th>
                        <th>{{ __('admin.common.date') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($checkouts as $checkout)
                        <tr>
                            <td>
                                @if ($checkout->business)
                                    <a href="{{ route('admin.businesses.show', $checkout->business) }}">{{ $checkout->business->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $checkout->plan?->name(app()->getLocale()) }} <span class="sub">{{ __('admin.periods.'.$checkout->billing_period->value) }}</span></td>
                            <td class="num ltr nowrap">{{ AdminFormat::money($checkout->amount_minor, $checkout->currency) }}</td>
                            <td class="mono ltr">{{ $checkout->provider }}</td>
                            <td>
                                @include('admin.partials.badge', ['tone' => AdminFormat::tone($checkout->status->value === 'expired' ? 'expired_link' : $checkout->status->value), 'label' => __('admin.checkout_status.'.$checkout->status->value)])
                                @if ($checkout->failure_reason)
                                    <span class="sub">{{ $checkout->failure_reason }}</span>
                                @endif
                            </td>
                            <td class="nowrap">{{ AdminFormat::dateTime($checkout->created_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">{{ __('admin.payments.no_checkouts') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.partials.pagination', ['paginator' => $checkouts])
        @else
            <form class="filters" method="get" action="{{ route('admin.payments.index') }}" data-auto-submit>
                <input type="hidden" name="tab" value="{{ $filters['tab'] }}">
                <div class="field grow">
                    <label for="q">{{ __('admin.common.search') }}</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="{{ __('admin.payments.search_placeholder') }}">
                </div>
                <div class="field">
                    <label for="source">{{ __('admin.payments.source') }}</label>
                    <select id="source" name="source">
                        <option value="">{{ __('admin.common.all') }}</option>
                        @foreach (['manual', 'gateway', 'admin'] as $source)
                            <option value="{{ $source }}" @selected($filters['source'] === $source)>{{ __('admin.payments.sources.'.$source) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="method">{{ __('admin.payments.method') }}</label>
                    <select id="method" name="method">
                        <option value="">{{ __('admin.common.all') }}</option>
                        @foreach ($methods as $method)
                            <option value="{{ $method }}" @selected($filters['method'] === $method)>{{ __('admin.methods.'.$method) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="from">{{ __('admin.common.from') }}</label>
                    <input id="from" name="from" type="date" value="{{ $filters['from'] }}">
                </div>
                <div class="field">
                    <label for="to">{{ __('admin.common.to') }}</label>
                    <input id="to" name="to" type="date" value="{{ $filters['to'] }}">
                </div>
                <div class="field">
                    <button type="submit" class="btn btn-primary">{{ __('admin.common.search') }}</button>
                </div>
            </form>
            @include('admin.payments.table', ['payments' => $payments, 'showBusiness' => true])
            @include('admin.partials.pagination', ['paginator' => $payments])
        @endif
    </div>
@endsection
