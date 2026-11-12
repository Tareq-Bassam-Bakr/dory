@extends('admin.layout')
@use('App\Support\AdminFormat')
@use('App\Support\Money')

@section('title', __('admin.overview.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('admin.overview.title') }}</h1>
            <p>{{ __('admin.overview.subtitle') }}</p>
        </div>
    </div>

    @unless ($onlinePayments)
        <div class="alert info">
            @include('admin.partials.icon', ['name' => 'info'])
            <div>{{ __('admin.overview.online_off') }}</div>
        </div>
    @endunless

    <div class="grid grid-4">
        @include('admin.partials.stat', [
            'icon' => 'users', 'label' => __('admin.overview.users'), 'href' => route('admin.users.index'),
            'value' => AdminFormat::number($counts['users']['total']),
            'hint' => __('admin.overview.users_hint', ['active' => $counts['users']['active'], 'suspended' => $counts['users']['suspended']]),
        ])
        @include('admin.partials.stat', [
            'icon' => 'store', 'label' => __('admin.overview.businesses'), 'href' => route('admin.businesses.index'),
            'value' => AdminFormat::number($counts['businesses']['total']),
            'hint' => __('admin.overview.businesses_hint', ['active' => $counts['businesses']['active'], 'suspended' => $counts['businesses']['suspended']]),
        ])
        @include('admin.partials.stat', [
            'icon' => 'overview', 'label' => __('admin.overview.branches'),
            'value' => AdminFormat::number($counts['branches']),
            'hint' => __('admin.overview.branches_hint', ['queues' => $counts['queues']]),
        ])
        @include('admin.partials.stat', [
            'icon' => 'layers', 'label' => __('admin.overview.paid'), 'tone' => 'success',
            'href' => route('admin.subscriptions.index', ['state' => 'paid']),
            'value' => AdminFormat::number($subscriptions['states']['paid']),
            'hint' => __('admin.overview.paid_hint', ['complimentary' => $subscriptions['states']['complimentary']]),
        ])
    </div>

    <div class="grid grid-4 section-gap">
        @include('admin.partials.stat', [
            'icon' => 'clock', 'label' => __('admin.overview.trials'),
            'href' => route('admin.subscriptions.index', ['state' => 'trialing']),
            'value' => AdminFormat::number($subscriptions['states']['trialing']),
        ])
        @include('admin.partials.stat', [
            'icon' => 'alert', 'label' => __('admin.overview.overdue'), 'tone' => $subscriptions['states']['past_due'] > 0 ? 'warning' : '',
            'href' => route('admin.subscriptions.index', ['state' => 'past_due']),
            'value' => AdminFormat::number($subscriptions['states']['past_due']),
            'hint' => __('admin.overview.overdue_hint'),
        ])
        @include('admin.partials.stat', [
            'icon' => 'layers', 'label' => __('admin.overview.expired'),
            'href' => route('admin.subscriptions.index', ['state' => 'expired']),
            'value' => AdminFormat::number($subscriptions['states']['expired']),
        ])
        @include('admin.partials.stat', [
            'icon' => 'wallet', 'label' => __('admin.overview.awaiting_approval'), 'tone' => $counts['awaiting_approval'] > 0 ? 'warning' : '',
            'href' => route('admin.payments.index', ['tab' => 'awaiting']),
            'value' => AdminFormat::number($counts['awaiting_approval']),
        ])
    </div>

    <div class="grid grid-main section-gap">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2>{{ __('admin.overview.revenue_title') }}</h2>
                    <p>{{ __('admin.overview.revenue_note') }}</p>
                </div>
                <a class="btn btn-secondary btn-small" href="{{ route('admin.revenue') }}">{{ __('admin.overview.see_all') }}</a>
            </div>
            @foreach ($revenue['currencies'] as $currency)
                @php
                    $series = [];
                    $labels = [];
                    $axis = [];
                    foreach ($revenue['months'] as $month) {
                        $series[$month] = $revenue['by_month'][$month][$currency]['total'] ?? 0;
                        $labels[$month] = Money::decimal($series[$month], $currency);
                        $axis[$month] = AdminFormat::month($month);
                    }
                    $total = $revenue['totals'][$currency]['total'] ?? 0;
                @endphp
                <p class="subtle">{{ $currency }} · {{ __('admin.revenue.total') }}: <strong class="ltr">{{ AdminFormat::money($total, $currency) }}</strong></p>
                @include('admin.partials.bar-chart', ['series' => $series, 'labels' => $labels, 'axis' => $axis, 'barClass' => 'bar', 'title' => __('admin.overview.revenue_title').' ('.$currency.')'])
            @endforeach
        </div>
        <div class="card">
            <div class="card-header"><h2>{{ __('admin.overview.states_title') }}</h2></div>
            @include('admin.partials.stacked-bar', [
                'title' => __('admin.overview.states_title'),
                'parts' => [
                    [__('admin.states.paid'), $subscriptions['states']['paid'], 'swatch-success'],
                    [__('admin.states.complimentary'), $subscriptions['states']['complimentary'], 'swatch-info'],
                    [__('admin.states.trialing'), $subscriptions['states']['trialing'], 'swatch-primary'],
                    [__('admin.states.past_due'), $subscriptions['states']['past_due'], 'swatch-warning'],
                    [__('admin.states.expired'), $subscriptions['states']['expired'], 'swatch-danger'],
                    [__('admin.states.free'), $subscriptions['states']['free'], 'swatch-neutral'],
                ],
            ])
            <h3 class="section-gap">{{ __('admin.overview.plans_title') }}</h3>
            <ul class="list">
                @forelse ($subscriptions['plans'] as $code => $count)
                    <li><span>{{ $planNames[$code] ?? $code }}</span><strong>{{ $count }}</strong></li>
                @empty
                    <li class="subtle">{{ __('admin.common.empty') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="grid grid-3 section-gap">
        <div class="card">
            <div class="card-header">
                <h2>{{ __('admin.overview.overdue_title') }}</h2>
                <a class="btn btn-ghost btn-small" href="{{ route('admin.subscriptions.index', ['state' => 'past_due']) }}">{{ __('admin.overview.see_all') }}</a>
            </div>
            <ul class="list">
                @forelse ($subscriptions['overdue'] as $row)
                    <li>
                        <a href="{{ route('admin.businesses.show', $row['business']) }}">{{ $row['business']->name }}</a>
                        <span class="subtle">{{ __('admin.overview.grace_until', ['date' => AdminFormat::date($row['grace_ends_at'])]) }}</span>
                    </li>
                @empty
                    <li class="subtle">{{ __('admin.overview.no_overdue') }}</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><h2>{{ __('admin.overview.trials_title') }}</h2></div>
            <ul class="list">
                @forelse ($subscriptions['trials_ending'] as $row)
                    <li>
                        <a href="{{ route('admin.businesses.show', $row['business']) }}">{{ $row['business']->name }}</a>
                        <span class="subtle">{{ __('admin.overview.trial_until', ['date' => AdminFormat::date($row['trial_ends_at'])]) }}</span>
                    </li>
                @empty
                    <li class="subtle">{{ __('admin.overview.no_trials') }}</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header">
                <h2>{{ __('admin.overview.approvals_title') }}</h2>
                <a class="btn btn-ghost btn-small" href="{{ route('admin.payments.index', ['tab' => 'awaiting']) }}">{{ __('admin.overview.see_all') }}</a>
            </div>
            <ul class="list">
                @forelse ($awaiting as $invoice)
                    <li>
                        <a href="{{ route('admin.payments.show', $invoice) }}">{{ $invoice->business?->name }}</a>
                        <span class="ltr nowrap">{{ AdminFormat::money($invoice->amount_minor, $invoice->currency) }}</span>
                    </li>
                @empty
                    <li class="subtle">{{ __('admin.overview.no_approvals') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="card section-gap">
        <div class="card-header"><h2>{{ __('admin.overview.growth_title') }}</h2></div>
        @php
            $axis = [];
            foreach (array_keys($growth) as $month) {
                $axis[$month] = AdminFormat::month($month);
            }
        @endphp
        {{-- Every chart variable is passed: an include sees the page's own
             variables, such as the revenue chart's labels above. --}}
        @include('admin.partials.bar-chart', ['series' => $growth, 'labels' => [], 'axis' => $axis, 'barClass' => 'bar-alt', 'title' => __('admin.overview.growth_title')])
    </div>
    <p class="subtle section-gap">{{ __('admin.common.utc_note') }}</p>
@endsection
