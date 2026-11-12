@extends('admin.layout')
@use('App\Support\AdminFormat')

@section('title', __('admin.plans.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('admin.plans.title') }}</h1>
            <p>{{ __('admin.plans.subtitle') }}</p>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('admin.plans.create') }}">@include('admin.partials.icon', ['name' => 'plus']) {{ __('admin.plans.new') }}</a>
        </div>
    </div>

    <div class="alert info">
        @include('admin.partials.icon', ['name' => 'info'])
        <div>{{ __('admin.plans.changes_note') }}</div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('admin.common.plan') }}</th>
                    <th>{{ __('admin.common.status') }}</th>
                    <th>{{ __('admin.plans.limits') }}</th>
                    <th>{{ __('admin.plans.features') }}</th>
                    <th>{{ __('admin.plans.prices') }}</th>
                    <th class="num">{{ __('admin.plans.subscribers') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($plans as $plan)
                    <tr>
                        <td>
                            <strong>{{ $plan->name(app()->getLocale()) }}</strong>
                            <span class="sub"><span class="mono ltr">{{ $plan->code }}</span> · {{ app()->getLocale() === 'ar' ? $plan->name_en : $plan->name_ar }}</span>
                        </td>
                        <td>
                            @include('admin.partials.badge', ['tone' => $plan->is_active ? 'success' : '', 'label' => $plan->is_active ? __('admin.plans.active') : __('admin.plans.inactive')])
                            <span class="sub">
                                {{ $plan->is_free ? __('admin.plans.free') : __('admin.plans.paid') }}
                                @if ($plan->code === $defaultPlan) · {{ __('admin.plans.default') }} @endif
                                @if ($plan->code === $trialPlan) · {{ __('admin.plans.trial') }} @endif
                            </span>
                        </td>
                        <td class="nowrap">
                            @foreach (['branches' => 'max_branches', 'queues' => 'max_queues', 'members' => 'max_members'] as $limit => $column)
                                <span class="sub">{{ __('admin.limits.'.$limit) }}: <strong>{{ $plan->{$column} ?? __('admin.common.unlimited') }}</strong></span>
                            @endforeach
                        </td>
                        <td>
                            @forelse ($plan->features ?? [] as $feature)
                                <span class="sub">{{ __('admin.features.'.$feature) }}</span>
                            @empty
                                <span class="subtle">—</span>
                            @endforelse
                        </td>
                        <td class="nowrap">
                            @forelse ($plan->prices as $price)
                                <span class="sub @unless ($price->is_active) muted @endunless">
                                    {{ __('admin.periods.'.$price->billing_period->value) }}:
                                    <strong class="ltr">{{ AdminFormat::money($price->amount_minor, $price->currency) }}</strong>
                                    @unless ($price->is_active) ({{ __('admin.plans.inactive') }}) @endunless
                                </span>
                            @empty
                                <span class="subtle">{{ $plan->is_free ? __('admin.plans.no_charge') : __('admin.plans.no_prices') }}</span>
                            @endforelse
                        </td>
                        <td class="num" data-subscribers="{{ $plan->code }}">{{ $subscribers[$plan->code] ?? 0 }}</td>
                        <td><a class="btn btn-secondary btn-small" href="{{ route('admin.plans.edit', $plan) }}">{{ __('admin.common.edit') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="subtle">{{ __('admin.plans.subscribers_note') }}</p>
    </div>
@endsection
