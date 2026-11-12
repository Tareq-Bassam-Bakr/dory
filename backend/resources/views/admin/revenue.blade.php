@extends('admin.layout')
@use('App\Support\AdminFormat')
@use('App\Support\Money')

@section('title', __('admin.revenue.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('admin.revenue.title') }}</h1>
            <p>{{ __('admin.revenue.subtitle') }}</p>
        </div>
        <form class="filters" method="get" action="{{ route('admin.revenue') }}" data-auto-submit>
            <div class="field">
                <label for="months">{{ __('admin.revenue.range') }}</label>
                <select id="months" name="months">
                    @foreach ([3, 6, 12, 24] as $option)
                        <option value="{{ $option }}" @selected($months === $option)>{{ trans_choice('admin.revenue.last_months', $option, ['count' => $option]) }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="alert info">
        @include('admin.partials.icon', ['name' => 'info'])
        <div>{{ __('admin.revenue.rule') }}</div>
    </div>

    @foreach ($revenue['currencies'] as $currency)
        @php
            $totals = $revenue['totals'][$currency] ?? ['manual' => 0, 'gateway' => 0, 'total' => 0, 'count' => 0];
            $series = [];
            $labels = [];
            $axis = [];
            foreach ($revenue['months'] as $month) {
                $series[$month] = $revenue['by_month'][$month][$currency]['total'] ?? 0;
                $labels[$month] = Money::decimal($series[$month], $currency);
                $axis[$month] = AdminFormat::month($month);
            }
        @endphp
        <h2 class="section-gap ltr">{{ $currency }}</h2>
        <div class="grid grid-4 section-gap">
            @include('admin.partials.stat', ['icon' => 'chart', 'label' => __('admin.revenue.total'), 'tone' => 'success', 'value' => AdminFormat::money($totals['total'], $currency), 'hint' => trans_choice('admin.revenue.payments_count', $totals['count'], ['count' => $totals['count']])])
            @include('admin.partials.stat', ['icon' => 'wallet', 'label' => __('admin.revenue.manual'), 'value' => AdminFormat::money($totals['manual'], $currency), 'hint' => __('admin.revenue.manual_hint')])
            @include('admin.partials.stat', ['icon' => 'card', 'label' => __('admin.revenue.gateway'), 'value' => AdminFormat::money($totals['gateway'], $currency), 'hint' => __('admin.revenue.gateway_hint')])
            @include('admin.partials.stat', ['icon' => 'clock', 'label' => __('admin.revenue.not_counted'), 'tone' => ($pending[$currency] ?? 0) > 0 ? 'warning' : '', 'value' => AdminFormat::money($pending[$currency] ?? 0, $currency), 'hint' => __('admin.revenue.not_counted_hint')])
        </div>

        <div class="grid grid-main section-gap">
            <div class="card">
                <div class="card-header"><h2>{{ __('admin.revenue.by_month') }}</h2></div>
                @include('admin.partials.bar-chart', ['series' => $series, 'labels' => $labels, 'axis' => $axis, 'barClass' => 'bar', 'title' => __('admin.revenue.by_month').' ('.$currency.')'])
                <div class="table-wrap section-gap">
                    <table>
                        <thead>
                        <tr>
                            <th>{{ __('admin.revenue.month') }}</th>
                            <th class="num">{{ __('admin.revenue.manual') }}</th>
                            <th class="num">{{ __('admin.revenue.gateway') }}</th>
                            <th class="num">{{ __('admin.revenue.total') }}</th>
                            <th class="num">{{ __('admin.revenue.count') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach (array_reverse($revenue['months']) as $month)
                            @php $row = $revenue['by_month'][$month][$currency] ?? ['manual' => 0, 'gateway' => 0, 'total' => 0, 'count' => 0]; @endphp
                            <tr>
                                <td class="nowrap">{{ AdminFormat::month($month) }}</td>
                                <td class="num ltr">{{ Money::decimal($row['manual'], $currency) }}</td>
                                <td class="num ltr">{{ Money::decimal($row['gateway'], $currency) }}</td>
                                <td class="num ltr"><strong>{{ Money::decimal($row['total'], $currency) }}</strong></td>
                                <td class="num">{{ $row['count'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>{{ __('admin.revenue.by_plan') }}</h2></div>
                <ul class="list">
                    @php $planRows = array_filter(array_map(fn ($byCurrency) => $byCurrency[$currency] ?? 0, $revenue['by_plan'])); arsort($planRows); @endphp
                    @forelse ($planRows as $code => $amount)
                        <li><span>{{ $planNames[$code] ?? $code }}</span><strong class="ltr">{{ AdminFormat::money($amount, $currency) }}</strong></li>
                    @empty
                        <li class="subtle">{{ __('admin.revenue.none') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endforeach
    <p class="subtle section-gap">{{ __('admin.common.utc_note') }}</p>
@endsection
