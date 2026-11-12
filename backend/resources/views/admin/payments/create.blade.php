@extends('admin.layout')
@use('App\Support\AdminFormat')

@section('title', __('admin.payments.record'))

@section('content')
    <div class="page-header">
        <div>
            <div class="breadcrumb"><a href="{{ route('admin.payments.index') }}">{{ __('admin.nav.payments') }}</a></div>
            <h1>{{ __('admin.payments.record') }}</h1>
            <p>{{ __('admin.payments.record_intro') }}</p>
        </div>
    </div>

    <div class="grid grid-main">
        <form class="card" method="post" action="{{ route('admin.payments.store') }}">
            @csrf
            <div class="form-grid">
                <div class="field full">
                    <label for="business">{{ __('admin.common.business') }}</label>
                    <input id="business" name="business" type="text" dir="ltr" required maxlength="120"
                           value="{{ old('business', $business?->public_id) }}" class="@error('business') is-invalid @enderror">
                    <span class="help">
                        @if ($business)
                            {{ __('admin.payments.for_business', ['name' => $business->name]) }}
                        @else
                            {{ __('admin.payments.business_help') }}
                        @endif
                    </span>
                    @error('business')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="plan">{{ __('admin.common.plan') }}</label>
                    <select id="plan" name="plan" required class="@error('plan') is-invalid @enderror">
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->code }}" @selected(old('plan') === $plan->code)>{{ $plan->name(app()->getLocale()) }}</option>
                        @endforeach
                    </select>
                    @error('plan')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="billing_period">{{ __('admin.payments.period') }}</label>
                    <select id="billing_period" name="billing_period" required>
                        @foreach ($periods as $period)
                            <option value="{{ $period }}" @selected(old('billing_period', 'monthly') === $period)>{{ __('admin.periods.'.$period) }}</option>
                        @endforeach
                    </select>
                    @error('billing_period')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="currency">{{ __('admin.plans.currency') }}</label>
                    <select id="currency" name="currency" required dir="ltr">
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency }}" @selected(old('currency', $defaultCurrency) === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                    @error('currency')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="amount">{{ __('admin.common.amount') }}</label>
                    <input id="amount" name="amount" type="text" inputmode="decimal" dir="ltr" value="{{ old('amount') }}"
                           placeholder="{{ __('admin.payments.amount_placeholder') }}" class="@error('amount') is-invalid @enderror">
                    <span class="help">{{ __('admin.payments.amount_help') }}</span>
                    @error('amount')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="method">{{ __('admin.payments.method') }}</label>
                    <select id="method" name="method" required>
                        @foreach ($methods as $method)
                            <option value="{{ $method }}" @selected(old('method', 'bank_transfer') === $method)>{{ __('admin.methods.'.$method) }}</option>
                        @endforeach
                    </select>
                    @error('method')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="received_at">{{ __('admin.payments.received_on') }}</label>
                    <input id="received_at" name="received_at" type="date" value="{{ old('received_at', now()->utc()->toDateString()) }}" max="{{ now()->utc()->toDateString() }}" required>
                    @error('received_at')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field full">
                    <label for="reference">{{ __('admin.payments.reference') }}</label>
                    <input id="reference" name="reference" type="text" dir="ltr" maxlength="120" value="{{ old('reference') }}" class="@error('reference') is-invalid @enderror">
                    <span class="help">{{ __('admin.payments.reference_help') }}</span>
                    @error('reference')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field full">
                    <label for="notes">{{ __('admin.common.notes') }}</label>
                    <textarea id="notes" name="notes" maxlength="500">{{ old('notes') }}</textarea>
                    @error('notes')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('admin.payments.record_submit') }}</button>
                <a class="btn btn-ghost" href="{{ $business ? route('admin.businesses.show', $business) : route('admin.payments.index') }}">{{ __('admin.common.cancel') }}</a>
            </div>
        </form>

        <div class="stack">
            <div class="card">
                <h2>{{ __('admin.payments.how_title') }}</h2>
                <ol class="timeline section-gap">
                    <li>{{ __('admin.payments.how_1') }}</li>
                    <li class="warning">{{ __('admin.payments.how_2') }}</li>
                    <li class="success">{{ __('admin.payments.how_3') }}</li>
                </ol>
                @if ($fourEyes)
                    <p class="subtle">{{ __('admin.payments.four_eyes_on') }}</p>
                @endif
            </div>
            <div class="card">
                <h2>{{ __('admin.plans.prices') }}</h2>
                <ul class="list section-gap">
                    @foreach ($plans as $plan)
                        @foreach ($plan->prices->where('is_active', true) as $price)
                            <li>
                                <span>{{ $plan->name(app()->getLocale()) }} <span class="sub">{{ __('admin.periods.'.$price->billing_period->value) }}</span></span>
                                <strong class="ltr">{{ AdminFormat::money($price->amount_minor, $price->currency) }}</strong>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
