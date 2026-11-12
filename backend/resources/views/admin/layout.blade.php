<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ __('admin.console') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('dory-admin/dory-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('dory-admin/admin.css') }}?v=2">
    <script src="{{ asset('dory-admin/admin.js') }}?v=2" defer></script>
</head>
<body>
<a class="visually-hidden" href="#content">{{ __('admin.skip') }}</a>
<div class="shell">
    <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true" tabindex="-1">
    @include('admin.partials.sidebar')
    <label for="nav-toggle" class="scrim" aria-hidden="true"></label>
    <div class="main">
        <header class="topbar">
            <label for="nav-toggle" class="menu-button" title="{{ __('admin.nav.menu') }}">
                @include('admin.partials.icon', ['name' => 'menu'])
                <span class="visually-hidden">{{ __('admin.nav.menu') }}</span>
            </label>
            <div class="spacer"></div>
            <nav class="lang" aria-label="{{ __('admin.nav.language') }}">
                <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" hreflang="en" lang="en" aria-current="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">English</a>
                <a href="{{ request()->fullUrlWithQuery(['lang' => 'ar']) }}" hreflang="ar" lang="ar" aria-current="{{ app()->getLocale() === 'ar' ? 'true' : 'false' }}">العربية</a>
            </nav>
            <form method="post" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-small">
                    @include('admin.partials.icon', ['name' => 'logout'])
                    {{ __('admin.nav.sign_out') }}
                </button>
            </form>
        </header>
        <main id="content" class="content">
            @include('admin.partials.flash')
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
