@extends('admin.layout')
@use('App\Support\AdminFormat')

@section('title', $user->name)

@section('content')
    <div class="page-header">
        <div>
            <div class="breadcrumb"><a href="{{ route('admin.users.index') }}">{{ __('admin.nav.users') }}</a></div>
            <h1>{{ $user->name }}</h1>
            <p>
                @include('admin.partials.badge', ['tone' => AdminFormat::tone($user->status->value), 'label' => __('admin.user_status.'.$user->status->value)])
                @if ($user->is_platform_admin)
                    @include('admin.partials.badge', ['tone' => 'info', 'label' => __('admin.users.roles.admin')])
                @endif
            </p>
        </div>
        <div class="actions">
            @if ($user->status->value === 'suspended' && $canManage)
                <form method="post" action="{{ route('admin.users.reactivate', $user) }}" data-confirm="{{ __('admin.users.reactivate_confirm') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('admin.users.reactivate') }}</button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card-header"><h2>{{ __('admin.users.profile') }}</h2></div>
                <dl class="kv">
                    <dt>{{ __('admin.common.id') }}</dt><dd class="mono ltr">{{ $user->public_id }}</dd>
                    <dt>{{ __('admin.users.email') }}</dt>
                    <dd>
                        <span class="ltr">{{ $user->email ?? '—' }}</span>
                        @if ($user->email)
                            <span class="sub">{{ $user->email_verified_at ? __('admin.users.verified', ['date' => AdminFormat::date($user->email_verified_at)]) : __('admin.users.not_verified') }}</span>
                        @endif
                    </dd>
                    <dt>{{ __('admin.users.phone') }}</dt><dd class="ltr">{{ $user->phone ?? '—' }}</dd>
                    <dt>{{ __('admin.users.language') }}</dt><dd>{{ $user->locale === 'ar' ? 'العربية' : 'English' }}</dd>
                    <dt>{{ __('admin.users.last_sign_in') }}</dt><dd>{{ AdminFormat::dateTime($user->last_login_at) }}</dd>
                    <dt>{{ __('admin.common.created') }}</dt><dd>{{ AdminFormat::dateTime($user->created_at) }}</dd>
                    <dt>{{ __('admin.users.sessions') }}</dt><dd>{{ trans_choice('admin.users.tokens', $tokens, ['count' => $tokens]) }}</dd>
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><h2>{{ __('admin.users.memberships') }}</h2></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>{{ __('admin.common.business') }}</th><th>{{ __('admin.users.role') }}</th><th>{{ __('admin.common.status') }}</th><th>{{ __('admin.users.joined') }}</th></tr></thead>
                        <tbody>
                        @forelse ($user->memberships as $membership)
                            <tr>
                                <td>
                                    @if ($membership->business)
                                        <a href="{{ route('admin.businesses.show', $membership->business) }}">{{ $membership->business->name }}</a>
                                        @if ($membership->business->trashed())
                                            <span class="sub">{{ __('admin.business_status.archived') }}</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ __('admin.roles.'.$membership->role->value) }}</td>
                                <td>{{ __('admin.membership_status.'.$membership->status->value) }}</td>
                                <td class="nowrap">{{ AdminFormat::date($membership->joined_at ?? $membership->created_at) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">{{ __('admin.users.no_memberships') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            @if ($canManage && $user->status->value === 'active')
                <form class="card" method="post" action="{{ route('admin.users.suspend', $user) }}" data-confirm="{{ __('admin.users.suspend_confirm') }}">
                    @csrf
                    <h2>{{ __('admin.users.suspend_title') }}</h2>
                    <p class="subtle">{{ __('admin.users.suspend_help') }}</p>
                    <div class="field">
                        <label for="suspend_reason">{{ __('admin.common.reason') }}</label>
                        <textarea id="suspend_reason" name="reason" maxlength="255" required class="@error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                        @error('reason')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="btn btn-danger">{{ __('admin.users.suspend') }}</button>
                </form>
            @elseif (! $canManage)
                <div class="card">
                    <h2>{{ __('admin.users.protected_title') }}</h2>
                    <p class="subtle">{{ $user->is_platform_admin ? __('admin.users.protected_admin') : __('admin.users.protected_self') }}</p>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h2>{{ __('admin.users.activity') }}</h2>
                    <a class="btn btn-ghost btn-small" href="{{ route('admin.audit', ['user' => $user->public_id]) }}">{{ __('admin.overview.see_all') }}</a>
                </div>
                <ul class="list">
                    @forelse ($activity as $entry)
                        <li>
                            <span>{{ __('admin.audit.actions.'.$entry->action->value) }} <span class="sub">{{ $entry->actor?->name ?? __('admin.audit.console') }}</span></span>
                            <span class="subtle nowrap">{{ AdminFormat::dateTime($entry->created_at) }}</span>
                        </li>
                    @empty
                        <li class="subtle">{{ __('admin.common.empty') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
