@php
    $links = [
        ['route' => 'admin.overview', 'match' => 'admin.overview', 'icon' => 'overview', 'label' => __('admin.nav.overview')],
        ['route' => 'admin.businesses.index', 'match' => 'admin.businesses.*', 'icon' => 'store', 'label' => __('admin.nav.businesses')],
        ['route' => 'admin.users.index', 'match' => 'admin.users.*', 'icon' => 'users', 'label' => __('admin.nav.users')],
    ];
    $billing = [
        ['route' => 'admin.subscriptions.index', 'match' => 'admin.subscriptions.*', 'icon' => 'layers', 'label' => __('admin.nav.subscriptions')],
        ['route' => 'admin.plans.index', 'match' => 'admin.plans.*', 'icon' => 'tag', 'label' => __('admin.nav.plans')],
        ['route' => 'admin.payments.index', 'match' => 'admin.payments.*', 'icon' => 'card', 'label' => __('admin.nav.payments'), 'count' => $awaitingApproval ?? 0],
        ['route' => 'admin.revenue', 'match' => 'admin.revenue', 'icon' => 'chart', 'label' => __('admin.nav.revenue')],
    ];
    $platform = [
        ['route' => 'admin.audit', 'match' => 'admin.audit', 'icon' => 'shield', 'label' => __('admin.nav.audit')],
        ['route' => 'admin.settings', 'match' => 'admin.settings*', 'icon' => 'settings', 'label' => __('admin.nav.settings')],
    ];
@endphp
<aside class="sidebar" aria-label="{{ __('admin.console') }}">
    <a class="brand" href="{{ route('admin.overview') }}">
        <img src="{{ asset('dory-admin/dory-icon.png') }}" alt="" width="40" height="40">
        <span><strong>DORY</strong><span>{{ __('admin.console') }}</span></span>
    </a>
    <nav class="nav">
        @foreach ($links as $link)
            <a href="{{ route($link['route']) }}" @if (request()->routeIs($link['match'])) aria-current="page" @endif>
                @include('admin.partials.icon', ['name' => $link['icon']]) {{ $link['label'] }}
            </a>
        @endforeach
        <div class="nav-section">{{ __('admin.nav.section_billing') }}</div>
        @foreach ($billing as $link)
            <a href="{{ route($link['route']) }}" @if (request()->routeIs($link['match'])) aria-current="page" @endif>
                @include('admin.partials.icon', ['name' => $link['icon']]) {{ $link['label'] }}
                @if (($link['count'] ?? 0) > 0)
                    <span class="count" title="{{ __('admin.overview.awaiting_approval') }}">{{ $link['count'] }}</span>
                @endif
            </a>
        @endforeach
        <div class="nav-section">{{ __('admin.nav.section_platform') }}</div>
        @foreach ($platform as $link)
            <a href="{{ route($link['route']) }}" @if (request()->routeIs($link['match'])) aria-current="page" @endif>
                @include('admin.partials.icon', ['name' => $link['icon']]) {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
    <div class="sidebar-footer">
        {{ __('admin.nav.signed_in_as') }}
        <span class="who">{{ auth('admin')->user()?->name }}</span>
        <span class="ltr">{{ auth('admin')->user()?->email }}</span>
    </div>
</aside>
