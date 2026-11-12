@extends('admin.layout')
@use('App\Support\AdminFormat')

@section('title', __('admin.businesses.title'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('admin.businesses.title') }}</h1>
            <p>{{ __('admin.businesses.subtitle') }}</p>
        </div>
    </div>

    <div class="card">
        <form class="filters" method="get" action="{{ route('admin.businesses.index') }}" data-auto-submit>
            <div class="field grow">
                <label for="q">{{ __('admin.common.search') }}</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="{{ __('admin.businesses.search_placeholder') }}">
            </div>
            <div class="field">
                <label for="status">{{ __('admin.common.status') }}</label>
                <select id="status" name="status">
                    <option value="">{{ __('admin.common.all') }}</option>
                    @foreach (['active', 'inactive', 'suspended', 'archived'] as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __('admin.business_status.'.$status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="review">{{ __('admin.businesses.review') }}</label>
                <select id="review" name="review">
                    <option value="">{{ __('admin.common.all') }}</option>
                    <option value="approved" @selected($filters['review'] === 'approved')>{{ __('admin.review.approved') }}</option>
                    <option value="pending" @selected($filters['review'] === 'pending')>{{ __('admin.review.pending') }}</option>
                </select>
            </div>
            <div class="field">
                <button type="submit" class="btn btn-primary">{{ __('admin.common.search') }}</button>
            </div>
            @if (array_filter($filters))
                <div class="field"><a class="btn btn-ghost" href="{{ route('admin.businesses.index') }}">{{ __('admin.common.reset') }}</a></div>
            @endif
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('admin.common.business') }}</th>
                    <th>{{ __('admin.common.owner') }}</th>
                    <th>{{ __('admin.common.status') }}</th>
                    <th>{{ __('admin.businesses.review') }}</th>
                    <th>{{ __('admin.businesses.plan_now') }}</th>
                    <th class="num">{{ __('admin.businesses.branches') }}</th>
                    <th>{{ __('admin.businesses.created') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($businesses as $business)
                    @php $owner = $business->members->first()?->user; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('admin.businesses.show', $business) }}"><strong>{{ $business->name }}</strong></a>
                            <span class="sub ltr">{{ $business->slug }}</span>
                        </td>
                        <td>
                            @if ($owner)
                                <a href="{{ route('admin.users.show', $owner) }}">{{ $owner->name }}</a>
                                <span class="sub ltr">{{ $owner->email }}</span>
                            @else
                                <span class="subtle">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($business->trashed())
                                @include('admin.partials.badge', ['tone' => '', 'label' => __('admin.business_status.archived')])
                            @else
                                @include('admin.partials.badge', ['tone' => AdminFormat::tone($business->status->value), 'label' => __('admin.business_status.'.$business->status->value)])
                            @endif
                        </td>
                        <td>
                            @if ($business->approved_at)
                                @include('admin.partials.badge', ['tone' => 'success', 'label' => __('admin.review.approved')])
                            @else
                                @include('admin.partials.badge', ['tone' => 'info', 'label' => __('admin.review.pending')])
                            @endif
                        </td>
                        <td>
                            @php $state = $states[$business->getKey()]; @endphp
                            {{ $state['plan'] }}
                            <span class="sub">{{ __('admin.states.'.$state['key']) }}</span>
                        </td>
                        <td class="num">{{ $business->branches_count }}</td>
                        <td class="nowrap">{{ AdminFormat::date($business->created_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">{{ __('admin.common.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $businesses])
    </div>
@endsection
