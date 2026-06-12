<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr' }}" @class(['dark' => ($appearance ?? 'system') == 'dark']) class="h-full bg-white">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

        {{-- Canonical app origin. The assistant-message renderer uses this to
             allow-list <img> hosts (chunk images live on the main domain even
             when the hosted chat runs on an agent subdomain) and strip every
             other image src, neutralising prompt-injection exfiltration pixels. --}}
        <meta name="app-url" content="{{ config('app.url') }}">

        @include('analytics')

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

        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title data-inertia>{{ config('app.name', 'ChatVia') }}</title>

        <!-- Preload fonts to prevent FOUC -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
        {{-- Rubik ships an Arabic subset (Google serves it via unicode-range, so
             no subset param is needed). app.css re-points the `font-sans` utility
             to Rubik whenever the document is Arabic / Farsi / RTL. --}}
        <link href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,300..700;1,300..700&display=swap" rel="stylesheet">

        @include('partials.cover')

        @routes

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased h-full">
        @inertia
    </body>
</html>
