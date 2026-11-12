@extends('admin.layout')

@section('title', $plan ? __('admin.plans.edit_title', ['plan' => $plan->name(app()->getLocale())]) : __('admin.plans.new'))

@section('content')
    @php $hasOld = session()->hasOldInput(); @endphp
    <div class="page-header">
        <div>
            <div class="breadcrumb"><a href="{{ route('admin.plans.index') }}">{{ __('admin.nav.plans') }}</a></div>
            <h1>{{ $plan ? __('admin.plans.edit_title', ['plan' => $plan->name(app()->getLocale())]) : __('admin.plans.new') }}</h1>
            @if ($plan && $subscribers > 0)
                <p>{{ trans_choice('admin.plans.affects', $subscribers, ['count' => $subscribers]) }}</p>
            @endif
        </div>
    </div>

    <form method="post" action="{{ $plan ? route('admin.plans.update', $plan) : route('admin.plans.store') }}"
          @if ($plan && $subscribers > 0) data-confirm="{{ __('admin.plans.save_confirm') }}" @endif>
        @csrf
        @if ($plan)
            @method('PUT')
        @endif

        <div class="grid grid-main">
            <div class="stack">
                <div class="card">
                    <h2>{{ __('admin.plans.details') }}</h2>
                    <div class="form-grid section-gap">
                        <div class="field">
                            <label for="code">{{ __('admin.plans.code') }}</label>
                            @if ($plan)
                                <input id="code" type="text" value="{{ $plan->code }}" disabled dir="ltr">
                                <span class="help">{{ __('admin.plans.code_fixed') }}</span>
                            @else
                                <input id="code" name="code" type="text" value="{{ old('code') }}" required maxlength="40" dir="ltr"
                                       pattern="[a-z][a-z0-9_\-]{1,39}" class="@error('code') is-invalid @enderror">
                                <span class="help">{{ __('admin.plans.code_help') }}</span>
                                @error('code')<span class="error">{{ $message }}</span>@enderror
                            @endif
                        </div>
                        <div class="field">
                            <span class="label">{{ __('admin.plans.kind') }}</span>
                            @if ($plan)
                                <span>{{ $plan->is_free ? __('admin.plans.free') : __('admin.plans.paid') }}</span>
                                <span class="help">{{ __('admin.plans.kind_fixed') }}</span>
                            @else
                                <label class="check"><input type="radio" name="is_free" value="0" @checked(old('is_free', '0') === '0')> {{ __('admin.plans.paid') }}</label>
                                <label class="check"><input type="radio" name="is_free" value="1" @checked(old('is_free') === '1')> {{ __('admin.plans.free') }}</label>
                                @error('is_free')<span class="error">{{ $message }}</span>@enderror
                            @endif
                        </div>
                        <div class="field">
                            <label for="name_en">{{ __('admin.plans.name_en') }}</label>
                            <input id="name_en" name="name_en" type="text" lang="en" dir="ltr" maxlength="60" required
                                   value="{{ old('name_en', $plan?->name_en) }}" class="@error('name_en') is-invalid @enderror">
                            @error('name_en')<span class="error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="name_ar">{{ __('admin.plans.name_ar') }}</label>
                            <input id="name_ar" name="name_ar" type="text" lang="ar" dir="rtl" maxlength="60" required
                                   value="{{ old('name_ar', $plan?->name_ar) }}" class="@error('name_ar') is-invalid @enderror">
                            @error('name_ar')<span class="error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="description_en">{{ __('admin.plans.description_en') }}</label>
                            <textarea id="description_en" name="description_en" lang="en" dir="ltr" maxlength="255">{{ old('description_en', $plan?->description_en) }}</textarea>
                            @error('description_en')<span class="error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="description_ar">{{ __('admin.plans.description_ar') }}</label>
                            <textarea id="description_ar" name="description_ar" lang="ar" dir="rtl" maxlength="255">{{ old('description_ar', $plan?->description_ar) }}</textarea>
                            @error('description_ar')<span class="error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2>{{ __('admin.plans.prices') }}</h2>
                    <p class="subtle section-gap">{{ __('admin.plans.prices_help', ['currencies' => implode(', ', $currencies)]) }}</p>
                    @error('prices')<div class="alert error">{{ $message }}</div>@enderror
                    <div class="table-wrap">
                        <table class="price-grid">
                            <thead>
                            <tr>
                                <th>{{ __('admin.plans.currency') }}</th>
                                @foreach ($periods as $period)
                                    <th>{{ __('admin.periods.'.$period) }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($currencies as $currency)
                                <tr>
                                    <th scope="row" class="ltr">{{ $currency }}</th>
                                    @foreach ($periods as $period)
                                        @php
                                            $key = $period.'_'.$currency;
                                            $existing = $prices[$key] ?? null;
                                        @endphp
                                        <td>
                                            <div class="field">
                                                <label class="visually-hidden" for="price_{{ $key }}">{{ __('admin.periods.'.$period) }} {{ $currency }}</label>
                                                <input id="price_{{ $key }}" name="prices[{{ $key }}][amount]" type="text" inputmode="decimal" dir="ltr"
                                                       value="{{ old('prices.'.$key.'.amount', $existing['amount'] ?? '') }}"
                                                       placeholder="{{ $existing ? '' : __('admin.plans.no_price') }}"
                                                       @disabled($plan?->is_free) class="@error('prices.'.$key.'.amount') is-invalid @enderror">
                                                @error('prices.'.$key.'.amount')<span class="error">{{ $message }}</span>@enderror
                                                @if ($existing)
                                                    <input type="hidden" name="prices[{{ $key }}][existing]" value="1">
                                                    <label class="check"><input type="checkbox" name="prices[{{ $key }}][active]" value="1" @checked($hasOld ? old('prices.'.$key.'.active') === '1' : $existing['active'])> {{ __('admin.plans.offered') }}</label>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($otherPrices !== [])
                        <p class="subtle">{{ __('admin.plans.other_currencies') }}</p>
                        <ul class="list">
                            @foreach ($otherPrices as $label)
                                <li class="ltr">{{ $label }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <h2>{{ __('admin.plans.limits') }}</h2>
                    <p class="subtle section-gap">{{ __('admin.plans.limits_help') }}</p>
                    @foreach (['branches' => 'max_branches', 'queues' => 'max_queues', 'members' => 'max_members'] as $limit => $column)
                        @php
                            $current = $plan?->{$column};
                            $unlimited = $hasOld ? old($column.'_unlimited') === '1' : ($plan !== null && $current === null);
                        @endphp
                        <div class="field">
                            <label for="{{ $column }}">{{ __('admin.limits.'.$limit) }}</label>
                            <input id="{{ $column }}" name="{{ $column }}" type="number" min="1" max="100000" dir="ltr"
                                   value="{{ old($column, $current) }}" class="@error($column) is-invalid @enderror">
                            <label class="check"><input type="checkbox" name="{{ $column }}_unlimited" value="1" @checked($unlimited)> {{ __('admin.common.unlimited') }}</label>
                            @error($column)<span class="error">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>

                <div class="card">
                    <h2>{{ __('admin.plans.features') }}</h2>
                    @foreach ($features as $feature)
                        <label class="check section-gap">
                            <input type="checkbox" name="features[]" value="{{ $feature }}" @checked(in_array($feature, $hasOld ? (array) old('features', []) : ($plan?->features ?? []), true))>
                            <span>{{ __('admin.features.'.$feature) }}<span class="sub">{{ __('admin.features.'.$feature.'_help') }}</span></span>
                        </label>
                    @endforeach
                    @error('features')<span class="error">{{ $message }}</span>@enderror
                </div>

                <div class="card">
                    <h2>{{ __('admin.plans.visibility') }}</h2>
                    <label class="check section-gap">
                        <input type="checkbox" name="is_active" value="1" @checked($hasOld ? old('is_active') === '1' : ($plan?->is_active ?? true)) @disabled($plan && $plan->code === $defaultPlan)>
                        <span>{{ __('admin.plans.active_label') }}<span class="sub">{{ $plan && $plan->code === $defaultPlan ? __('admin.plans.default_always_active') : __('admin.plans.active_help') }}</span></span>
                    </label>
                    @if ($plan && $plan->code === $defaultPlan)
                        <input type="hidden" name="is_active" value="1">
                    @endif
                    @error('is_active')<span class="error">{{ $message }}</span>@enderror
                    <div class="field section-gap">
                        <label for="sort_order">{{ __('admin.plans.sort_order') }}</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" max="1000" dir="ltr" value="{{ old('sort_order', $plan?->sort_order ?? 10) }}">
                        <span class="help">{{ __('admin.plans.sort_help') }}</span>
                        @error('sort_order')<span class="error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">{{ __('admin.common.save') }}</button>
                    <a class="btn btn-ghost" href="{{ route('admin.plans.index') }}">{{ __('admin.common.cancel') }}</a>
                </div>
            </div>
        </div>
    </form>
@endsection
