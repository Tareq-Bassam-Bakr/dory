<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title') · DORY</title>
    <style>
        /* DORY blue and white (lib/app/theme/app_colors.dart). */
        :root {
            --bg: #eff6ff; --card: #ffffff; --text: #0f172a; --muted: #475569;
            --primary: #1557a6; --primary-text: #ffffff; --border: #e2e8f0;
            --success: #15803d; --success-bg: #dcfce7; --warn: #b45309; --warn-bg: #fef3c7;
            --error: #dc2626; --error-bg: #fee2e2;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1626; --card: #13233a; --text: #e8eef7; --muted: #a7b6c9;
                --primary: #93c5fd; --primary-text: #0b2a52; --border: #24364f;
                --success: #86efac; --success-bg: #12301f; --warn: #fcd34d; --warn-bg: #3a2a0c;
                --error: #fca5a5; --error-bg: #3a1714;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--text);
            font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Arabic", Tahoma, sans-serif;
        }
        main { max-width: 480px; margin: 0 auto; padding: 16px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .brand { font-weight: 800; letter-spacing: .1em; color: var(--primary); font-size: 20px; }
        .lang a { color: var(--muted); margin-inline-start: 12px; text-decoration: none; font-size: 14px; }
        .lang a[aria-current="true"] { color: var(--text); font-weight: 600; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 16px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        .muted { color: var(--muted); font-size: 14px; }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-top: 1px solid var(--border); }
        .row:first-of-type { border-top: 0; }
        .amount { font-weight: 700; direction: ltr; unicode-bidi: isolate; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 14px; font-weight: 700; margin-bottom: 12px; }
        .badge.ok { background: var(--success-bg); color: var(--success); }
        .badge.wait { background: var(--warn-bg); color: var(--warn); }
        .badge.bad { background: var(--error-bg); color: var(--error); }
        button, .button {
            display: block; width: 100%; margin-top: 18px; padding: 14px; border: 0; border-radius: 12px;
            background: var(--primary); color: var(--primary-text); font-size: 17px; font-weight: 700;
            text-align: center; text-decoration: none; cursor: pointer;
        }
        button[disabled] { opacity: .6; cursor: progress; }
        footer { text-align: center; color: var(--muted); font-size: 13px; margin: 24px 0; }
    </style>
</head>
<body>
<main>
    <header>
        <span class="brand">DORY</span>
        <nav class="lang">
            <a href="?{{ http_build_query(array_merge(request()->query(), ['lang' => 'en'])) }}" hreflang="en" aria-current="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">English</a>
            <a href="?{{ http_build_query(array_merge(request()->query(), ['lang' => 'ar'])) }}" hreflang="ar" aria-current="{{ app()->getLocale() === 'ar' ? 'true' : 'false' }}">العربية</a>
        </nav>
    </header>
    @yield('content')
    <footer>{{ __('billing.secure_note') }}</footer>
</main>
</body>
</html>
