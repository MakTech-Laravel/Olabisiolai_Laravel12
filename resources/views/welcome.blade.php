<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ config('app.name', 'Gidira') }} API — backend services for the Gidira platform.">
        <title>{{ config('app.name', 'Gidira') }} API</title>
        <style>
            :root {
                color-scheme: light dark;
                --bg: #f7f7f5;
                --card: #ffffff;
                --ink: #1b1b18;
                --muted: #5c5c57;
                --border: #e3e3e0;
                --accent: #0f6b4d;
                --accent-ink: #ffffff;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --bg: #0f0f0e;
                    --card: #171716;
                    --ink: #f4f4f0;
                    --muted: #a8a8a0;
                    --border: #2d2d2a;
                    --accent: #2f9e72;
                    --accent-ink: #06150f;
                }
            }

            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
                font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
                background: var(--bg);
                color: var(--ink);
            }
            main {
                width: 100%;
                max-width: 36rem;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 1rem;
                padding: 2rem;
            }
            h1 {
                margin: 0 0 0.75rem;
                font-size: 2rem;
                line-height: 1.2;
                font-weight: 700;
            }
            p {
                margin: 0 0 1.5rem;
                color: var(--muted);
                line-height: 1.6;
            }
            .actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
            }
            a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 2.5rem;
                padding: 0 1rem;
                border-radius: 0.5rem;
                text-decoration: none;
                font-weight: 600;
                font-size: 0.95rem;
            }
            .primary {
                background: var(--accent);
                color: var(--accent-ink);
            }
            .secondary {
                border: 1px solid var(--border);
                color: var(--ink);
                background: transparent;
            }
        </style>
    </head>
    <body>
        <main>
            <h1>{{ config('app.name', 'Gidira') }} API</h1>
            <p>
                Backend API for the {{ config('app.name', 'Gidira') }} platform.
                Use the interactive documentation to explore available endpoints.
            </p>
            <div class="actions">
                <a class="primary" href="{{ url('/api/documentation') }}">API documentation</a>
                <a class="secondary" href="{{ url('/api/v1') }}">API base</a>
            </div>
        </main>
    </body>
</html>
