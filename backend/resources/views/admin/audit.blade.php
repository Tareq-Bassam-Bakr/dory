@extends('admin.layout')
@use('App\Support\AdminFormat')

@section('title', __('admin.audit.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('admin.audit.title') }}</h1>
            <p>{{ __('admin.audit.subtitle') }}</p>
        </div>
    </div>

    <div class="card">
        <form class="filters" method="get" action="{{ route('admin.audit') }}" data-auto-submit>
            <div class="field">
                <label for="action">{{ __('admin.audit.action') }}</label>
                <select id="action" name="action">
                    <option value="">{{ __('admin.common.all') }}</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ __('admin.audit.actions.'.$action) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field grow">
                <label for="actor">{{ __('admin.audit.actor') }}</label>
                <input id="actor" name="actor" type="search" dir="ltr" value="{{ $filters['actor'] }}" placeholder="{{ __('admin.audit.actor_placeholder') }}">
            </div>
            <div class="field">
                <label for="from">{{ __('admin.common.from') }}</label>
                <input id="from" name="from" type="date" value="{{ $filters['from'] }}">
            </div>
            <div class="field">
                <label for="to">{{ __('admin.common.to') }}</label>
                <input id="to" name="to" type="date" value="{{ $filters['to'] }}">
            </div>
            @if ($filters['business'])
                <input type="hidden" name="business" value="{{ $filters['business'] }}">
            @endif
            @if ($filters['user'])
                <input type="hidden" name="user" value="{{ $filters['user'] }}">
            @endif
            <div class="field">
                <button type="submit" class="btn btn-primary">{{ __('admin.common.search') }}</button>
            </div>
            @if (array_filter($filters))
                <div class="field"><a class="btn btn-ghost" href="{{ route('admin.audit') }}">{{ __('admin.common.reset') }}</a></div>
            @endif
        </form>

        @if ($scope)
            <p class="subtle">{{ $scope }}</p>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('admin.common.date') }}</th>
                    <th>{{ __('admin.audit.action') }}</th>
                    <th>{{ __('admin.audit.actor') }}</th>
                    <th>{{ __('admin.common.business') }}</th>
                    <th>{{ __('admin.audit.details') }}</th>
                    <th>{{ __('admin.audit.ip') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="nowrap">{{ AdminFormat::dateTime($entry->created_at) }}</td>
                        <td>
                            @include('admin.partials.badge', ['tone' => $entry->action->tone(), 'label' => __('admin.audit.actions.'.$entry->action->value)])
                        </td>
                        <td>
                            @if ($entry->actor)
                                <a href="{{ route('admin.users.show', $entry->actor) }}">{{ $entry->actor->name }}</a>
                                <span class="sub ltr">{{ $entry->actor->email }}</span>
                            @else
                                {{ $entry->actor_label === 'console' ? __('admin.audit.console') : ($entry->actor_label ?? __('admin.audit.system')) }}
                            @endif
                        </td>
                        <td>
                            @if ($entry->business)
                                <a href="{{ route('admin.businesses.show', $entry->business) }}">{{ $entry->business->name }}</a>
                            @else
                                <span class="subtle">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($entry->properties)
                                <pre class="json">{{ json_encode($entry->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            @else
                                <span class="subtle">—</span>
                            @endif
                        </td>
                        <td class="mono ltr nowrap">{{ $entry->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ __('admin.common.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $entries])
        <p class="subtle">{{ __('admin.audit.retention_note') }}</p>
    </div>
@endsection
