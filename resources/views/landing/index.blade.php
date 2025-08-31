<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>ChatVIA - Home</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    @vite(['resources/css/landing.css'])

    @include('partials.cover')

    <style>
        @keyframes pulseSoft { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: .6; transform: scale(1.2); } }
        .pulse-dot { background-color: #10b981; animation: pulseSoft 1.2s ease-in-out infinite; }

        /* CookieConsent v3 Custom Styles */
        .cc-window {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .cc-compliance {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }

        .cc-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .cc-accept-all {
            background: #10b981;
            color: white;
        }

        .cc-accept-all:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .cc-accept-necessary {
            background: #f3f4f6;
            color: #374151;
        }

        .cc-accept-necessary:hover {
            background: #e5e7eb;
        }

        .cc-message {
            color: #374151;
            line-height: 1.6;
        }
    </style>

    @include('analytics')

    <link rel="icon" type="image/png" href="{{ asset('images/favicons/favicon-96x96.png') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicons/favicon.svg') }}" />
    <link rel="shortcut icon" href="{{ asset('images/favicons/favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicons/apple-touch-icon.png') }}" />
    <meta name="apple-mobile-web-app-title" content="ChatVia.ai" />
    <link rel="manifest" href="{{ asset('images/favicons/site.webmanifest') }}" />

</head>

<body class="font-inter antialiased bg-white text-zinc-900 tracking-tight scroll-smooth overflow-x-hidden">

<!-- Page wrapper -->
<div class="flex flex-col min-h-screen overflow-hidden supports-[overflow:clip]:overflow-clip">

    @include('partials.landing.header')

    <!-- Page content -->
    <main class="grow">

        @include('partials.landing.hero')

        @include('partials.landing.features')

        @include('partials.landing.features-grid')

        @if(request()->get('ref') !== 'reddit')
            @include('partials.landing.pricing')
        @endif

        @include('partials.landing.faqs')

@include('partials.landing.cta')

</main>

@include('partials.landing.footer')

</div>

@include('partials.landing.scripts')

@if(\Illuminate\Support\Facades\App::isProduction())
<script
    id="chatbot-embed-script"
    src="https://cdn.chatvia.ai/widget/embed.js"
    agent-id="e8c75295bf6572"
    async>
</script>
@endif
</body>

</html>
