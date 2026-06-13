<!DOCTYPE html>
<html lang="{{ LaravelLocalization::getCurrentLocale() }}" dir="{{ LaravelLocalization::getCurrentLocaleDirection() }}">

<head>
    <meta charset="utf-8">
    <title>@yield('title', config('app.name'))</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    {{-- SEO: advertise only the locales that are actually translated (those with a
         lang/{code}/landing.php). The other supported locales are still reachable +
         switchable, but fall back to English, so we don't list them as alternates. --}}
    @foreach (LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
        @if (file_exists(lang_path($localeCode.'/landing.php')))
            <link rel="alternate" hreflang="{{ $localeCode }}" href="{{ LaravelLocalization::getLocalizedURL($localeCode) }}" />
        @endif
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ LaravelLocalization::getLocalizedURL(LaravelLocalization::getDefaultLocale()) }}" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
        rel="stylesheet">
    {{-- Rubik ships an Arabic subset; load it only on Arabic / Farsi (RTL) pages so
         the Arabic script renders correctly — English pages stay on Inter. --}}
    @if (in_array(LaravelLocalization::getCurrentLocale(), ['ar', 'fa'], true))
        <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @endif

    @include('partials.cover')

    @vite(['resources/css/landing.css'])

    @stack('preload')

    <style>
        @keyframes marquee {
            0% {
                transform: translateX(0%);
            }

            100% {
                transform: translateX(-50%);
            }
        }
    </style>

</head>

<body class="font-inter antialiased bg-white text-slate-800 tracking-normal">

    @yield('content')

    @include('landing.partials.scripts')
    @include('landing.partials.cookie-banner')

    @stack('scripts')

    @stack('preload')
</body>

</html>
