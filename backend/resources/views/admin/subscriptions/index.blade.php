@extends('admin.layout')
@use('App\Support\AdminFormat')

@section('title', __('admin.subscriptions.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('admin.subscriptions.title') }}</h1>
            <p>{{ __('admin.subscriptions.subtitle') }}</p>
        </div>
    </div>

    <nav class="tabs" aria-label="{{ __('admin.subscriptions.title') }}">
        <a href="{{ route('admin.subscriptions.index', array_filter(['plan' => $filters['plan'], 'q' => $filters['q']])) }}" @if ($filters['state'] === '') aria-current="page" @endif>
            {{ __('admin.common.all') }} ({{ array_sum($totals) }})
        </a>
        @foreach ($totals as $key => $count)
            <a href="{{ route('admin.subscriptions.index', array_filter(['state' => $key, 'plan' => $filters['plan'], 'q' => $filters['q']])) }}" @if ($filters['state'] === $key) aria-current="page" @endif>
                {{ __('admin.states.'.$key) }} ({{ $count }})
            </a>
        @endforeach
    </nav>

    <div class="card">
        <form class="filters" method="get" action="{{ route('admin.subscriptions.index') }}" data-auto-submit>
            <input type="hidden" name="state" value="{{ $filters['state'] }}">
            <div class="field grow">
                <label for="q">{{ __('admin.common.search') }}</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="{{ __('admin.businesses.search_placeholder') }}">
            </div>
            <div class="field">
                <label for="plan">{{ __('admin.common.plan') }}</label>
                <select id="plan" name="plan">
                    <option value="">{{ __('admin.common.all') }}</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->code }}" @selected($filters['plan'] === $plan->code)>{{ $plan->name(app()->getLocale()) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <button type="submit" class="btn btn-primary">{{ __('admin.common.search') }}</button>
            </div>
        </form>

        <p class="subtle">{{ __('admin.subscriptions.computed_note') }}</p>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('admin.common.business') }}</th>
                    <th>{{ __('admin.subscriptions.state') }}</th>
                    <th>{{ __('admin.subscriptions.plan_now') }}</th>
                    <th>{{ __('admin.subscriptions.until_col') }}</th>
                    <th class="num">{{ __('admin.common.amount') }}</th>
                    <th>{{ __('admin.subscriptions.renewal') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    @php $state = $row['state']; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('admin.businesses.show', $row['business']) }}"><strong>{{ $row['business']->name }}</strong></a>
                            <span class="sub ltr">{{ $row['business']->slug }}</span>
                        </td>
                        <td>@include('admin.partials.badge', ['tone' => AdminFormat::tone($row['key']), 'label' => __('admin.states.'.$row['key'])])</td>
                        <td>
                            {{ $state->plan->name(app()->getLocale()) }}
                            @if ($state->subscribedPlan->code !== $state->plan->code)
                                <span class="sub">{{ __('admin.subscriptions.on_record', ['plan' => $state->subscribedPlan->name(app()->getLocale())]) }}</span>
                            @endif
                        </td>
                        <td class="nowrap">
                            @switch ($row['key'])
                                @case('trialing') {{ AdminFormat::date($state->trialEndsAt) }} @break
                                @case('paid') {{ AdminFormat::date($state->periodEnd) }} @break
                                @case('complimentary') {{ AdminFormat::date($state->periodEnd) }} @break
                                @case('past_due') {{ __('admin.subscriptions.grace_short', ['date' => AdminFormat::date($state->graceEndsAt)]) }} @break
                                @default <span class="subtle">—</span>
                            @endswitch
                        </td>
                        <td class="num ltr nowrap">{{ $state->amountMinor !== null && $state->currency && $state->isPaid() ? AdminFormat::money($state->amountMinor, $state->currency) : '—' }}</td>
                        <td>
                            @if ($state->isPaid() && $state->cancelAtPeriodEnd)
                                @include('admin.partials.badge', ['tone' => 'warning', 'label' => __('admin.subscriptions.ends_at_period_end')])
                            @elseif ($state->scheduledPlan)
                                <span class="subtle">{{ __('admin.subscriptions.moves_to', ['plan' => $state->scheduledPlan->name(app()->getLocale())]) }}</span>
                            @elseif ($state->isPaid())
                                <span class="subtle">{{ __('admin.periods.'.($state->billingPeriod?->value ?? 'monthly')) }}</span>
                            @else
                                <span class="subtle">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ __('admin.common.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $rows])
    </div>
@endsection
