<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('admin.auth.submit') }} · {{ __('admin.console') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('dory-admin/dory-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('dory-admin/admin.css') }}?v=2">
    <script src="{{ asset('dory-admin/admin.js') }}?v=2" defer></script>
</head>
<body>
<main class="auth">
    <div class="card">
        <nav class="lang" aria-label="{{ __('admin.nav.language') }}">
            <a href="?lang=en" hreflang="en" lang="en" aria-current="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">English</a>
            <a href="?lang=ar" hreflang="ar" lang="ar" aria-current="{{ app()->getLocale() === 'ar' ? 'true' : 'false' }}">العربية</a>
        </nav>
        <div class="brand">
            <img src="{{ asset('dory-admin/dory-icon.png') }}" alt="DORY" width="48" height="48">
            <span><strong>DORY</strong><span>{{ __('admin.console') }}</span></span>
        </div>
        <h1>{{ __('admin.auth.title') }}</h1>
        <p class="muted">{{ __('admin.auth.intro') }}</p>

        @if (session('status'))
            <div class="alert info" role="status">{{ session('status') }}</div>
        @endif

        <form method="post" action="{{ route('admin.login.store') }}" novalidate>
            @csrf
            <div class="field">
                <label for="email">{{ __('admin.auth.email') }}</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus
                       value="{{ old('email') }}" class="@error('email') is-invalid @enderror" dir="ltr">
                @error('email')<span class="error" role="alert">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password">{{ __('admin.auth.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required dir="ltr"
                       class="@error('password') is-invalid @enderror">
                @error('password')<span class="error" role="alert">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="btn btn-primary btn-block">{{ __('admin.auth.submit') }}</button>
        </form>
        <p class="note">{{ __('admin.auth.note', ['minutes' => (int) config('admin.session.idle_minutes')]) }}</p>
    </div>
</main>
</body>
</html>
