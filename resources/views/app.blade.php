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

        {{-- Public Reverb app key, for builds without VITE_REVERB_APP_KEY
             (the Docker image). The browser sees this key anyway. --}}
        <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">

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

        <title data-inertia>{{ config('chatvia.brand.name') }}</title>

        {{-- Fonts are self-hosted: a third-party font CDN would receive every
             visitor's IP address. Arabic / Farsi text uses the system font. --}}
        <link href="{{ asset('fonts/inter/inter.css') }}" rel="stylesheet">

        @include('partials.meta')

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased h-full">
        @inertia
    </body>
</html>
