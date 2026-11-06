@extends('guest.layout')

@section('title', $queue['name'])

@section('content')
    <section class="card">
        <h2>{{ $queue['business']['name'] }} · {{ $queue['branch']['name'] }}</h2>
        <h1>{{ $queue['name'] }}</h1>
        @if ($queue['service'])
            <p class="muted">{{ $queue['service']['name'] }}</p>
        @endif
        <span class="badge {{ $queue['accepting_joins'] ? '' : 'off' }}">{{ __('guest.status_'.$queue['status']) }}</span>

        <div class="stats">
            <div class="stat">
                <strong>{{ $queue['waiting_count'] }}</strong>
                <span class="muted">{{ trans_choice('guest.waiting_count', $queue['waiting_count'], ['count' => $queue['waiting_count']]) }}</span>
            </div>
            <div class="stat">
                <strong>~{{ $queue['estimated_wait']['minutes'] }}</strong>
                <span class="muted">{{ trans_choice('guest.estimated_wait', $queue['estimated_wait']['minutes'], ['minutes' => $queue['estimated_wait']['minutes']]) }}</span>
            </div>
        </div>
        <p class="muted">{{ __('guest.estimate_note') }}</p>

        @if (count($queue['now_serving']) > 0)
            <h2>{{ __('guest.now_serving') }}</h2>
            <div class="serving">
                @foreach ($queue['now_serving'] as $number)
                    <span>{{ $number }}</span>
                @endforeach
            </div>
        @endif
    </section>

    @if (isset($fieldErrors['form']))
        <div class="alert" role="alert">{{ $fieldErrors['form'] }}</div>
    @endif

    @if ($held)
        <div class="notice">
            {{ __('guest.held_ticket', ['number' => $held->ticket_number]) }}
            <a class="button" href="{{ route('guest.ticket', $held->public_id) }}">{{ __('guest.view_ticket') }}</a>
        </div>
    @elseif (! $queue['allow_guest_join'])
        <div class="alert">{{ __('guest.join_disabled') }}</div>
    @elseif ($queue['accepting_joins'])
        <section class="card">
            <h1>{{ __('guest.join_title') }}</h1>
            <form method="post" action="{{ route('guest.join.store', $token) }}" novalidate>
                <input type="hidden" name="_form" value="{{ $formNonce }}">
                <input type="hidden" name="_key" value="{{ $idempotencyKey }}">

                <label for="name">
                    {{ __('guest.name_label') }}
                    @unless ($queue['require_guest_name']) <span class="muted">{{ __('guest.optional') }}</span> @endunless
                </label>
                <input id="name" name="name" type="text" maxlength="80" autocomplete="name"
                       value="{{ $old['name'] ?? '' }}" @if ($queue['require_guest_name']) required @endif>
                @isset($fieldErrors['name'])<div class="field-error">{{ $fieldErrors['name'] }}</div>@endisset

                <label for="phone">
                    {{ __('guest.phone_label') }}
                    @unless ($queue['require_guest_phone']) <span class="muted">{{ __('guest.optional') }}</span> @endunless
                </label>
                <input id="phone" name="phone" type="tel" dir="ltr" maxlength="20" autocomplete="tel"
                       placeholder="+963944123456" value="{{ $old['phone'] ?? '' }}"
                       @if ($queue['require_guest_phone']) required @endif>
                <div class="muted">{{ __('guest.phone_hint') }}</div>
                @isset($fieldErrors['phone'])<div class="field-error">{{ $fieldErrors['phone'] }}</div>@endisset

                <label class="check">
                    <input type="checkbox" name="consent" value="1" @if (! empty($old['consent'])) checked @endif>
                    <span>{{ __('guest.consent_label', ['business' => $queue['business']['name']]) }}</span>
                </label>
                @isset($fieldErrors['consent'])<div class="field-error">{{ $fieldErrors['consent'] }}</div>@endisset

                <button type="submit">{{ __('guest.join_button') }}</button>
            </form>
        </section>
    @endif
@endsection
