@extends('admin.layout')
@use('App\Support\AdminFormat')

@section('title', __('admin.users.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('admin.users.title') }}</h1>
            <p>{{ __('admin.users.subtitle') }}</p>
        </div>
    </div>

    <div class="card">
        <form class="filters" method="get" action="{{ route('admin.users.index') }}" data-auto-submit>
            <div class="field grow">
                <label for="q">{{ __('admin.common.search') }}</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="{{ __('admin.users.search_placeholder') }}">
            </div>
            <div class="field">
                <label for="status">{{ __('admin.common.status') }}</label>
                <select id="status" name="status">
                    <option value="">{{ __('admin.common.all') }}</option>
                    @foreach (['active', 'suspended', 'deactivated'] as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __('admin.user_status.'.$status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="role">{{ __('admin.users.role') }}</label>
                <select id="role" name="role">
                    <option value="">{{ __('admin.common.all') }}</option>
                    @foreach (['admin', 'business', 'customer'] as $role)
                        <option value="{{ $role }}" @selected($filters['role'] === $role)>{{ __('admin.users.roles.'.$role) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <button type="submit" class="btn btn-primary">{{ __('admin.common.search') }}</button>
            </div>
            @if (array_filter($filters))
                <div class="field"><a class="btn btn-ghost" href="{{ route('admin.users.index') }}">{{ __('admin.common.reset') }}</a></div>
            @endif
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('admin.users.user') }}</th>
                    <th>{{ __('admin.common.status') }}</th>
                    <th>{{ __('admin.users.role') }}</th>
                    <th class="num">{{ __('admin.users.businesses') }}</th>
                    <th>{{ __('admin.users.last_sign_in') }}</th>
                    <th>{{ __('admin.common.created') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <a href="{{ route('admin.users.show', $user) }}"><strong>{{ $user->name }}</strong></a>
                            <span class="sub ltr">{{ $user->email ?? $user->phone ?? '—' }}</span>
                        </td>
                        <td>@include('admin.partials.badge', ['tone' => AdminFormat::tone($user->status->value), 'label' => __('admin.user_status.'.$user->status->value)])</td>
                        <td>
                            @if ($user->is_platform_admin)
                                @include('admin.partials.badge', ['tone' => 'info', 'label' => __('admin.users.roles.admin')])
                            @elseif ($user->memberships_count > 0)
                                {{ __('admin.users.roles.business') }}
                            @else
                                <span class="subtle">{{ __('admin.users.roles.customer') }}</span>
                            @endif
                        </td>
                        <td class="num">{{ $user->memberships_count }}</td>
                        <td class="nowrap">{{ AdminFormat::dateTime($user->last_login_at) }}</td>
                        <td class="nowrap">{{ AdminFormat::date($user->created_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ __('admin.common.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $users])
    </div>
@endsection
