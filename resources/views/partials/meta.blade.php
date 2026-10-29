{{-- Favicons and basic meta tags for every app page (dashboard, sign-in and
     hosted chats), named after the app (Settings → Platform). The favicons
     are the install's own when it has an icon ({@see \App\Admin\Settings\CustomLogo}),
     else the ChatVia.ai ones. Page titles come from each Inertia page. --}}
@php($favicons = app(\App\Admin\Settings\CustomLogo::class))
<link rel="icon" type="image/png" href="{{ $favicons->favicon('favicon-96x96.png') }}" sizes="96x96" />
@if ($svg = $favicons->favicon('favicon.svg'))
    <link rel="icon" type="image/svg+xml" href="{{ $svg }}" />
@endif
<link rel="shortcut icon" href="{{ $favicons->favicon('favicon.ico') }}" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ $favicons->favicon('apple-touch-icon.png') }}" />
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}" />
<link rel="manifest" href="{{ route('web-manifest') }}" />

<meta property="og:type" content="website" />
<meta property="og:site_name" content="{{ config('app.name') }}" />
<meta property="og:title" content="{{ config('app.name') }}" />
