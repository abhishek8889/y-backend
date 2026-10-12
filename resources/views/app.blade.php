<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark', 'initial-loading'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            /* Critical CSS: initial page loader (before Vite/React boot) */
            #initial-loader {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 12px;
                background: #ffffff;
            }

            #initial-loader .squares-loader__dots {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
            }

            #initial-loader .squares-loader__dots span {
                display: block;
                width: 8px;
                height: 8px;
                background: #000000;
                border-radius: 0;
                opacity: 0.2;
                animation: squares-loader-pulse 1s ease-in-out infinite;
            }

            #initial-loader .squares-loader__dots span:nth-child(1) {
                animation-delay: 0s;
            }

            #initial-loader .squares-loader__dots span:nth-child(2) {
                animation-delay: 0.2s;
            }

            #initial-loader .squares-loader__dots span:nth-child(3) {
                animation-delay: 0.4s;
            }

            #initial-loader .squares-loader__label {
                margin: 0;
                color: #000000;
                font-size: 12px;
                font-weight: 500;
                letter-spacing: 0.16em;
                text-transform: uppercase;
                font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            }

            @keyframes squares-loader-pulse {
                0%,
                100% {
                    opacity: 0.2;
                }

                50% {
                    opacity: 1;
                }
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <div id="initial-loader" aria-live="polite" aria-label="Loading">
            <div class="squares-loader__dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <p class="squares-loader__label">Loading</p>
        </div>

        <x-inertia::app />
    </body>
</html>
