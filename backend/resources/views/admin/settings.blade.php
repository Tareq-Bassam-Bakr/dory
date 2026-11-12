@extends('admin.layout')
@use('App\Support\AdminFormat')
@use('Illuminate\Support\Carbon')

@section('title', __('admin.settings.title'))

@section('content')
    @php
        $hasOld = session()->hasOldInput();
        $value = fn (string $field) => $hasOld ? old($field) : $settings[$field]['value'];
        $shown = fn (mixed $v): string => match (true) {
            is_bool($v) => $v ? __('admin.common.on') : __('admin.common.off'),
            is_array($v) => implode(', ', $v),
            $v === null || $v === '' => '—',
            default => (string) $v,
        };
    @endphp
    <div class="page-header">
        <div>
            <h1>{{ __('admin.settings.title') }}</h1>
            <p>{{ __('admin.settings.subtitle') }}</p>
        </div>
    </div>

    <div class="grid grid-main">
        <form class="card" method="post" action="{{ route('admin.settings.update') }}" data-confirm="{{ __('admin.settings.save_confirm') }}">
            @csrf
            @method('PUT')

            @foreach ($groups as $group => $fields)
                <fieldset>
                    <legend>{{ __('admin.settings.groups.'.$group) }}</legend>
                    @foreach ($fields as $field)
                        @php $setting = $settings[$field]; $type = $definitions[$field]['type']; @endphp
                        <div class="field">
                            @if ($type === 'bool')
                                <input type="hidden" name="{{ $field }}" value="0">
                                <label class="check">
                                    <input type="checkbox" name="{{ $field }}" value="1" @checked($hasOld ? old($field) === '1' : (bool) $setting['value'])>
                                    <span>{{ __('admin.settings.fields.'.$field) }}<span class="sub">{{ __('admin.settings.help.'.$field) }}</span></span>
                                </label>
                            @elseif ($type === 'plan')
                                <label for="{{ $field }}">{{ __('admin.settings.fields.'.$field) }}</label>
                                <select id="{{ $field }}" name="{{ $field }}" class="@error($field) is-invalid @enderror">
                                    @foreach ($paidPlans as $plan)
                                        <option value="{{ $plan->code }}" @selected($value($field) === $plan->code)>{{ $plan->name(app()->getLocale()) }}</option>
                                    @endforeach
                                </select>
                                <span class="help">{{ __('admin.settings.help.'.$field) }}</span>
                            @elseif ($type === 'currency')
                                <label for="{{ $field }}">{{ __('admin.settings.fields.'.$field) }}</label>
                                <select id="{{ $field }}" name="{{ $field }}" dir="ltr" class="@error($field) is-invalid @enderror">
                                    @foreach ($currencyChoices as $currency)
                                        <option value="{{ $currency }}" @selected($value($field) === $currency)>{{ $currency }}</option>
                                    @endforeach
                                </select>
                                <span class="help">{{ __('admin.settings.help.'.$field) }}</span>
                            @elseif ($type === 'currencies')
                                <label for="{{ $field }}">{{ __('admin.settings.fields.'.$field) }}</label>
                                <input id="{{ $field }}" name="{{ $field }}" type="text" dir="ltr" maxlength="60"
                                       value="{{ $hasOld ? old($field) : implode(', ', (array) $setting['value']) }}" class="@error($field) is-invalid @enderror">
                                <span class="help">{{ __('admin.settings.help.'.$field) }}</span>
                            @else
                                <label for="{{ $field }}">{{ __('admin.settings.fields.'.$field) }}</label>
                                <input id="{{ $field }}" name="{{ $field }}" type="number" dir="ltr"
                                       min="{{ $definitions[$field]['min'] }}" max="{{ $definitions[$field]['max'] }}"
                                       value="{{ $value($field) }}" class="@error($field) is-invalid @enderror">
                                <span class="help">{{ __('admin.settings.help.'.$field) }}</span>
                            @endif
                            @error($field)<span class="error">{{ $message }}</span>@enderror
                            @foreach ($errors->get($field.'.*') as $messages)
                                <span class="error">{{ implode(' ', $messages) }}</span>
                            @endforeach
                            <span class="help">
                                {{ __('admin.settings.default', ['value' => $shown($setting['default'])]) }}
                                @if ($setting['overridden'])
                                    · @include('admin.partials.badge', ['tone' => 'info', 'label' => __('admin.settings.overridden')])
                                    {{ __('admin.settings.changed_by', ['name' => $setting['updated_by'] ?? '—', 'date' => AdminFormat::dateTime($setting['updated_at'] ? Carbon::parse($setting['updated_at']) : null)]) }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </fieldset>
            @endforeach

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('admin.common.save') }}</button>
            </div>
        </form>

        <div class="stack">
            <div class="card">
                <h2>{{ __('admin.settings.server_title') }}</h2>
                <p class="subtle section-gap">{{ __('admin.settings.server_note') }}</p>
                <dl class="kv">
                    @foreach ($environment as $item)
                        <dt>{{ $item['label'] }}</dt>
                        <dd>
                            @if (isset($item['tone']))
                                @include('admin.partials.badge', ['tone' => $item['tone'], 'label' => $item['value']])
                            @else
                                <span class="ltr">{{ $item['value'] }}</span>
                            @endif
                            @if (! empty($item['hint']))
                                <span class="sub">{{ $item['hint'] }}</span>
                            @endif
                        </dd>
                    @endforeach
                </dl>
            </div>
            <div class="card">
                <h2>{{ __('admin.settings.docs_title') }}</h2>
                <ul class="list section-gap">
                    <li><span class="ltr mono">docs/ADMIN_DASHBOARD.md</span></li>
                    <li><span class="ltr mono">docs/PRODUCTION_READINESS.md</span></li>
                    <li><span class="ltr mono">docs/PAYMENT_GATEWAY_CHECKLIST.md</span></li>
                    <li><span class="ltr mono">docs/COMMERCIAL_DECISIONS.md</span></li>
                </ul>
            </div>
        </div>
    </div>
@endsection
