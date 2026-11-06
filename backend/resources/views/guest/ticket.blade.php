@extends('guest.layout')

@section('title', $ticket->ticket_number)

@section('content')
    @if ($error)
        <div class="alert" role="alert">{{ $error }}</div>
    @endif

    <section class="card">
        <h2>{{ $queue->branch->business->name }} · {{ $queue->branch->name }}</h2>
        <h1>{{ $queue->name }}</h1>

        <p class="muted" style="text-align:center;margin-bottom:0">{{ __('guest.your_ticket') }}</p>
        <div class="number">{{ $ticket->ticket_number }}</div>

        <div class="{{ $ticket->status->value === 'called' ? 'notice' : ($ticket->status->isActive() ? '' : 'alert') }}" role="status" style="text-align:center;font-weight:600">
            {{ __('guest.status.'.$ticket->status->value) }}
        </div>

        @if ($ticket->status->value === 'called' && $arriveBy)
            <p style="text-align:center">{{ __('guest.arrive_by', ['time' => $arriveBy->copy()->setTimezone($queue->branch->timezone ?: 'UTC')->format('H:i')]) }}</p>
        @endif

        @if ($position['position'] !== null)
            <div class="stats">
                <div class="stat">
                    <strong>{{ $position['position'] }}</strong>
                    <span class="muted">{{ trans_choice('guest.people_ahead', $position['people_ahead'], ['count' => $position['people_ahead']]) }}</span>
                </div>
                <div class="stat">
                    <strong>~{{ $position['estimated_wait']['minutes'] }}</strong>
                    <span class="muted">{{ trans_choice('guest.estimated_wait', $position['estimated_wait']['minutes'], ['minutes' => $position['estimated_wait']['minutes']]) }}</span>
                </div>
            </div>
            <p class="muted">{{ __('guest.estimate_note') }}</p>
        @endif

        @if (count($nowServing) > 0)
            <h2>{{ __('guest.now_serving') }}</h2>
            <div class="serving">
                @foreach ($nowServing as $number)
                    <span>{{ $number }}</span>
                @endforeach
            </div>
        @endif
    </section>

    @if ($ticket->status->isActive())
        <p class="muted">{{ __('guest.keep_page') }} {{ __('guest.auto_refresh', ['seconds' => $refresh]) }}</p>
        <a class="button" href="{{ route('guest.ticket', $ticket->public_id) }}">{{ __('guest.refresh_now') }}</a>
    @endif

    @if ($ticket->status->value === 'waiting')
        <form method="post" action="{{ route('guest.ticket.cancel', $ticket->public_id) }}"
              onsubmit="return confirm(@js(__('guest.cancel_confirm')))">
            <button type="submit" class="secondary">{{ __('guest.cancel_button') }}</button>
        </form>
    @endif
@endsection
