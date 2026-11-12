@extends('admin.layout')
@use('App\Support\AdminFormat')
@use('App\Services\Billing\SubscriptionState')

@section('title', $business->name)

@section('content')
    <div class="page-header">
        <div>
            <div class="breadcrumb"><a href="{{ route('admin.businesses.index') }}">{{ __('admin.nav.businesses') }}</a></div>
            <h1>{{ $business->name }}</h1>
            <p>
                @if ($business->trashed())
                    @include('admin.partials.badge', ['tone' => '', 'label' => __('admin.business_status.archived')])
                @else
                    @include('admin.partials.badge', ['tone' => AdminFormat::tone($business->status->value), 'label' => __('admin.business_status.'.$business->status->value)])
                @endif
                @include('admin.partials.badge', ['tone' => $business->approved_at ? 'success' : 'info', 'label' => $business->approved_at ? __('admin.review.approved') : __('admin.review.pending')])
                @include('admin.partials.badge', ['tone' => AdminFormat::tone($stateKey), 'label' => $state->plan->name(app()->getLocale()).' · '.__('admin.states.'.$stateKey)])
            </p>
        </div>
        <div class="actions">
            @if (! $business->approved_at && ! $business->trashed())
                <form method="post" action="{{ route('admin.businesses.approve', $business) }}" data-confirm="{{ __('admin.businesses.approve_confirm') }}">
                    @csrf
                    <button type="submit" class="btn btn-success">@include('admin.partials.icon', ['name' => 'check']) {{ __('admin.businesses.approve') }}</button>
                </form>
            @endif
            @if ($business->status->value === 'suspended')
                <form method="post" action="{{ route('admin.businesses.reactivate', $business) }}" data-confirm="{{ __('admin.businesses.reactivate_confirm') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('admin.businesses.reactivate') }}</button>
                </form>
            @endif
            @unless ($business->trashed())
                <a class="btn btn-secondary" href="{{ route('admin.payments.create', ['business' => $business->public_id]) }}">@include('admin.partials.icon', ['name' => 'plus']) {{ __('admin.payments.record') }}</a>
            @endunless
        </div>
    </div>

    @if ($business->status->value === 'suspended')
        <div class="alert warning">
            @include('admin.partials.icon', ['name' => 'alert'])
            <div>
                <strong>{{ __('admin.businesses.suspended_since', ['date' => AdminFormat::dateTime($business->suspended_at)]) }}</strong><br>
                {{ __('admin.common.reason') }}: {{ $business->suspension_reason ?: '—' }}
            </div>
        </div>
    @endif

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card-header"><h2>{{ __('admin.businesses.profile') }}</h2></div>
                <dl class="kv">
                    <dt>{{ __('admin.common.id') }}</dt><dd class="mono ltr">{{ $business->public_id }}</dd>
                    <dt>{{ __('admin.businesses.slug') }}</dt><dd class="ltr">{{ $business->slug }}</dd>
                    <dt>{{ __('admin.businesses.category') }}</dt><dd>{{ AdminFormat::translated($business->category?->name) }}</dd>
                    <dt>{{ __('admin.common.owner') }}</dt>
                    <dd>
                        @if ($owner)
                            <a href="{{ route('admin.users.show', $owner) }}">{{ $owner->name }}</a> · <span class="ltr">{{ $owner->email }}</span>
                        @else
                            —
                        @endif
                    </dd>
                    <dt>{{ __('admin.businesses.contact') }}</dt>
                    <dd><span class="ltr">{{ $business->email ?: '—' }}</span> · <span class="ltr">{{ $business->phone ?: '—' }}</span></dd>
                    <dt>{{ __('admin.businesses.timezone') }}</dt><dd class="ltr">{{ $business->timezone }}</dd>
                    <dt>{{ __('admin.common.created') }}</dt><dd>{{ AdminFormat::dateTime($business->created_at) }}</dd>
                    <dt>{{ __('admin.businesses.review') }}</dt>
                    <dd>
                        @if ($business->approved_at)
                            {{ __('admin.businesses.approved_by', ['date' => AdminFormat::date($business->approved_at), 'name' => $business->approvedBy?->name ?? '—']) }}
                        @else
                            {{ __('admin.businesses.not_reviewed') }}
                        @endif
                        <span class="sub">{{ $approvalRequired ? __('admin.businesses.approval_required_note') : __('admin.businesses.approval_optional_note') }}</span>
                    </dd>
                </dl>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2>{{ __('admin.businesses.subscription_title') }}</h2>
                        <p>{{ __('admin.subscriptions.computed_note') }}</p>
                    </div>
                    @include('admin.partials.badge', ['tone' => AdminFormat::tone($stateKey), 'label' => __('admin.states.'.$stateKey)])
                </div>
                <dl class="kv">
                    <dt>{{ __('admin.subscriptions.plan_now') }}</dt><dd>{{ $state->plan->name(app()->getLocale()) }}</dd>
                    <dt>{{ __('admin.subscriptions.plan_on_record') }}</dt><dd>{{ $state->subscribedPlan->name(app()->getLocale()) }}</dd>
                    @if ($state->status === SubscriptionState::TRIALING)
                        <dt>{{ __('admin.subscriptions.trial_ends') }}</dt><dd>{{ AdminFormat::dateTime($state->trialEndsAt) }}</dd>
                    @endif
                    @if ($state->isPaid())
                        <dt>{{ __('admin.subscriptions.paid_until') }}</dt><dd>{{ AdminFormat::dateTime($state->periodEnd) }}</dd>
                        <dt>{{ __('admin.common.amount') }}</dt>
                        <dd class="ltr">{{ $state->amountMinor !== null && $state->currency ? AdminFormat::money($state->amountMinor, $state->currency) : '—' }} · {{ $state->billingPeriod ? __('admin.periods.'.$state->billingPeriod->value) : '' }}</dd>
                    @endif
                    @if ($state->graceEndsAt)
                        <dt>{{ __('admin.subscriptions.grace_ends') }}</dt><dd>{{ AdminFormat::dateTime($state->graceEndsAt) }}</dd>
                    @endif
                    @if ($state->cancelAtPeriodEnd && $state->isPaid())
                        <dt>{{ __('admin.subscriptions.canceled') }}</dt><dd>{{ __('admin.subscriptions.ends_at_period_end') }}</dd>
                    @endif
                    @if ($state->scheduledPlan)
                        <dt>{{ __('admin.subscriptions.scheduled') }}</dt><dd>{{ $state->scheduledPlan->name(app()->getLocale()) }}</dd>
                    @endif
                    @foreach (['branches', 'queues', 'members'] as $limit)
                        <dt>{{ __('admin.limits.'.$limit) }}</dt>
                        <dd>{{ $usage[$limit] }} / {{ $limits[$limit] ?? __('admin.common.unlimited') }}</dd>
                    @endforeach
                </dl>

                @unless ($business->trashed())
                    <div class="grid grid-2 section-gap">
                        @if (! ($state->status === SubscriptionState::ACTIVE && $state->isPaid()))
                            <form method="post" action="{{ route('admin.subscriptions.trial', $business) }}" class="card">
                                @csrf
                                <h3>{{ __('admin.subscriptions.extend_trial') }}</h3>
                                <div class="field">
                                    <label for="trial_days">{{ __('admin.subscriptions.days') }}</label>
                                    <input id="trial_days" name="days" type="number" min="1" max="90" value="{{ old('days', 14) }}" required>
                                    @error('days')<span class="error">{{ $message }}</span>@enderror
                                </div>
                                <div class="field">
                                    <label for="trial_plan">{{ __('admin.common.plan') }}</label>
                                    <select id="trial_plan" name="plan">
                                        <option value="">{{ __('admin.subscriptions.trial_plan_default') }}</option>
                                        @foreach ($paidPlans as $plan)
                                            <option value="{{ $plan->code }}">{{ $plan->name(app()->getLocale()) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-secondary">{{ __('admin.subscriptions.extend_trial') }}</button>
                            </form>
                        @endif

                        <form method="post" action="{{ route('admin.subscriptions.plan', $business) }}" class="card" data-confirm="{{ __('admin.subscriptions.assign_confirm') }}">
                            @csrf
                            <h3>{{ __('admin.subscriptions.assign') }}</h3>
                            <p class="subtle">{{ __('admin.subscriptions.assign_help') }}</p>
                            <div class="field">
                                <label for="assign_plan">{{ __('admin.common.plan') }}</label>
                                <select id="assign_plan" name="plan" required>
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->code }}">{{ $plan->name(app()->getLocale()) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="assign_until">{{ __('admin.subscriptions.until') }}</label>
                                <input id="assign_until" name="until" type="date" value="{{ old('until') }}">
                                <span class="help">{{ __('admin.subscriptions.until_help') }}</span>
                                @error('until')<span class="error">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="assign_note">{{ __('admin.common.notes') }}</label>
                                <input id="assign_note" name="note" type="text" maxlength="255" value="{{ old('note') }}">
                            </div>
                            <button type="submit" class="btn btn-secondary">{{ __('admin.subscriptions.assign') }}</button>
                        </form>
                    </div>

                    <div class="actions section-gap">
                        @if ($stateKey === 'paid' && ($state->cancelAtPeriodEnd || $state->scheduledPlan))
                            <form class="inline-form" method="post" action="{{ route('admin.subscriptions.resume', $business) }}" data-confirm="{{ __('admin.subscriptions.resume_confirm') }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-small">{{ __('admin.subscriptions.resume') }}</button>
                            </form>
                        @endif
                        @if (! $state->isComplimentary() && ($state->status === SubscriptionState::TRIALING || $state->status === SubscriptionState::PAST_DUE || ($state->isPaid() && ! $state->cancelAtPeriodEnd)))
                            <form class="inline-form" method="post" action="{{ route('admin.subscriptions.cancel', $business) }}" data-confirm="{{ __('admin.subscriptions.cancel_confirm') }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-small">{{ __('admin.subscriptions.cancel') }}</button>
                            </form>
                        @endif
                        @if ($state->status !== SubscriptionState::EXPIRED && ! $state->subscribedPlan->is_free)
                            <form class="inline-form" method="post" action="{{ route('admin.subscriptions.end', $business) }}" data-confirm="{{ __('admin.subscriptions.end_confirm') }}">
                                @csrf
                                <input type="hidden" name="reason" value="admin">
                                <button type="submit" class="btn btn-danger btn-small">{{ __('admin.subscriptions.end') }}</button>
                            </form>
                        @endif
                    </div>
                @endunless
            </div>
        </div>

        <div class="stack">
            @if (in_array($business->status->value, ['active', 'inactive'], true) && ! $business->trashed())
                <form class="card" method="post" action="{{ route('admin.businesses.suspend', $business) }}" data-confirm="{{ __('admin.businesses.suspend_confirm') }}">
                    @csrf
                    <h2>{{ __('admin.businesses.suspend_title') }}</h2>
                    <p class="subtle">{{ __('admin.businesses.suspend_help') }}</p>
                    <div class="field">
                        <label for="suspend_reason">{{ __('admin.common.reason') }}</label>
                        <textarea id="suspend_reason" name="reason" maxlength="255" required class="@error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                        @error('reason')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="btn btn-danger">{{ __('admin.businesses.suspend') }}</button>
                </form>
            @endif

            <div class="card">
                <div class="card-header"><h2>{{ __('admin.businesses.branches_title') }}</h2></div>
                <ul class="list">
                    @forelse ($business->branches as $branch)
                        <li>
                            <span>{{ $branch->name }} <span class="sub">{{ $branch->city }}</span></span>
                            <span class="subtle">{{ trans_choice('admin.businesses.queues_count', $branch->queues_count, ['count' => $branch->queues_count]) }}</span>
                        </li>
                    @empty
                        <li class="subtle">{{ __('admin.businesses.no_branches') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="card">
                <div class="card-header"><h2>{{ __('admin.businesses.team_title') }}</h2></div>
                <ul class="list">
                    @foreach ($business->members as $member)
                        <li>
                            <span>
                                @if ($member->user)
                                    <a href="{{ route('admin.users.show', $member->user) }}">{{ $member->user->name }}</a>
                                @else
                                    —
                                @endif
                            </span>
                            <span class="subtle">{{ __('admin.roles.'.$member->role->value) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="card section-gap">
        <div class="card-header"><h2>{{ __('admin.businesses.payments_title') }}</h2></div>
        @include('admin.payments.table', ['payments' => $invoices, 'showBusiness' => false])
    </div>

    <div class="grid grid-2 section-gap">
        <div class="card">
            <div class="card-header"><h2>{{ __('admin.businesses.history_title') }}</h2></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ __('admin.common.date') }}</th><th>{{ __('admin.subscriptions.event') }}</th><th>{{ __('admin.common.plan') }}</th><th>{{ __('admin.audit.actor') }}</th></tr></thead>
                    <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td class="nowrap">{{ AdminFormat::dateTime($event->occurred_at) }}</td>
                            <td>{{ __('admin.events.'.$event->type->value) }}</td>
                            <td>{{ $planLabels[$event->to_plan_id] ?? '—' }}</td>
                            <td>{{ $actors[$event->actor_id]->name ?? __('admin.audit.system') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">{{ __('admin.common.empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2>{{ __('admin.businesses.activity_title') }}</h2>
                <a class="btn btn-ghost btn-small" href="{{ route('admin.audit', ['business' => $business->public_id]) }}">{{ __('admin.overview.see_all') }}</a>
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
@endsection
