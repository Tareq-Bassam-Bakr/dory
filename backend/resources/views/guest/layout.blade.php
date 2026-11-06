<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    @if (! empty($refresh))
        <meta http-equiv="refresh" content="{{ (int) $refresh }}">
    @endif
    <title>@yield('title') · {{ __('guest.brand') }}</title>
    <style>
        :root {
            --bg: #f4f6f8; --card: #ffffff; --text: #17202a; --muted: #5d6d7e;
            --primary: #0f766e; --primary-text: #ffffff; --border: #d5dde5;
            --danger: #b42318; --danger-bg: #fef3f2; --notice-bg: #ecfdf3; --notice: #067647;
            --warn-bg: #fffaeb; --warn: #b54708;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1419; --card: #1a222b; --text: #e8edf2; --muted: #a3b1bf;
                --primary: #2dd4bf; --primary-text: #062b27; --border: #2c3a47;
                --danger: #fda29b; --danger-bg: #3a1714; --notice-bg: #0f2e22; --notice: #75e0a7;
                --warn-bg: #3a2a0c; --warn: #fdb022;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--text);
            font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Arabic", Tahoma, sans-serif;
        }
        main { max-width: 480px; margin: 0 auto; padding: 16px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .brand { font-weight: 700; letter-spacing: .08em; color: var(--primary); }
        .lang a { color: var(--muted); margin-inline-start: 12px; text-decoration: none; font-size: 14px; }
        .lang a[aria-current="true"] { color: var(--text); font-weight: 600; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 16px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 16px; margin: 0 0 8px; color: var(--muted); font-weight: 600; }
        .muted { color: var(--muted); font-size: 14px; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 13px; font-weight: 600; background: var(--notice-bg); color: var(--notice); }
        .badge.off { background: var(--warn-bg); color: var(--warn); }
        .stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
        .stat { border: 1px solid var(--border); border-radius: 12px; padding: 12px; }
        .stat strong { display: block; font-size: 20px; }
        .number { font-size: 56px; font-weight: 800; letter-spacing: .04em; text-align: center; margin: 8px 0; direction: ltr; }
        .serving { display: flex; flex-wrap: wrap; gap: 8px; direction: ltr; }
        .serving span { border: 1px solid var(--border); border-radius: 8px; padding: 2px 10px; font-weight: 700; }
        label { display: block; font-weight: 600; margin: 14px 0 6px; }
        input[type=text], input[type=tel] {
            width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;
            background: var(--bg); color: var(--text); font-size: 16px;
        }
        .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; }
        .check input { margin-top: 4px; width: 20px; height: 20px; }
        button, .button {
            display: block; width: 100%; margin-top: 18px; padding: 14px; border: 0; border-radius: 12px;
            background: var(--primary); color: var(--primary-text); font-size: 17px; font-weight: 700;
            text-align: center; text-decoration: none; cursor: pointer;
        }
        button.secondary { background: transparent; color: var(--danger); border: 1px solid var(--border); }
        .field-error { color: var(--danger); font-size: 14px; margin-top: 4px; }
        .alert { background: var(--danger-bg); color: var(--danger); border-radius: 12px; padding: 12px; margin-bottom: 16px; }
        .notice { background: var(--notice-bg); color: var(--notice); border-radius: 12px; padding: 12px; margin-bottom: 16px; }
        footer { text-align: center; color: var(--muted); font-size: 13px; margin: 24px 0; }
    </style>
</head>
<body>
<main>
    <header>
        <span class="brand">{{ __('guest.brand') }}</span>
        <nav class="lang">
            <a href="?lang=en" hreflang="en" aria-current="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">English</a>
            <a href="?lang=ar" hreflang="ar" aria-current="{{ app()->getLocale() === 'ar' ? 'true' : 'false' }}">العربية</a>
        </nav>
    </header>
    @yield('content')
    <footer>{{ __('guest.powered_by') }}</footer>
</main>
</body>
</html>
