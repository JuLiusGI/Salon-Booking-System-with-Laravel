{{--
    The error page shell.

    Styles are inlined on purpose. An error page is exactly the moment the built
    stylesheet may be missing — a failed deploy, a cleared build directory — so it
    must not depend on Vite having produced anything. The colours are the brand
    palette, written literally for that reason.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') &middot; {{ config('app.name') }}</title>
    <style>
        :root {
            --primary: #0a3323;
            --secondary: #105666;
            --accent: #d3968c;
            --canvas: #f7f4d5;
            --surface: #ffffff;
            --ink-muted: #4a5b52;
            --line: #e2ddb8;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: var(--canvas);
            color: var(--primary);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
        }

        .card {
            width: 100%;
            max-width: 30rem;
            padding: 2.5rem;
            text-align: center;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 1rem;
        }

        .code {
            margin: 0;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--secondary);
        }

        h1 {
            margin: 0.75rem 0 0;
            font-size: 1.75rem;
            font-weight: 600;
            line-height: 1.25;
        }

        .rule {
            width: 3rem;
            height: 3px;
            margin: 1.25rem auto;
            border-radius: 999px;
            background: var(--accent);
        }

        p { margin: 0; color: var(--ink-muted); }

        .actions {
            margin-top: 1.75rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }

        a {
            display: inline-block;
            padding: 0.6rem 1.25rem;
            border-radius: 999px;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .primary { background: var(--primary); color: var(--canvas); }
        .primary:hover { background: #14513a; }

        .secondary { border: 1px solid var(--line); color: var(--primary); }
        .secondary:hover { background: var(--canvas); }
    </style>
</head>
<body>
    <main class="card">
        <p class="code">Error @yield('code')</p>
        <h1>@yield('title')</h1>
        <div class="rule"></div>
        <p>@yield('message')</p>

        <div class="actions">
            <a class="primary" href="{{ url('/') }}">Back to the salon</a>
            @yield('extra')
        </div>
    </main>
</body>
</html>
