{{-- Favicons and basic meta tags for every app page (dashboard, sign-in and
     hosted chats), named after the app (Settings → Platform). Page titles
     come from each Inertia page. --}}
<link rel="icon" type="image/png" href="{{ asset('images/favicons/favicon-96x96.png') }}" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="{{ asset('images/favicons/favicon.svg') }}" />
<link rel="shortcut icon" href="{{ asset('images/favicons/favicon.ico') }}" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicons/apple-touch-icon.png') }}" />
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}" />
<link rel="manifest" href="{{ asset('images/favicons/site.webmanifest') }}" />

<meta property="og:type" content="website" />
<meta property="og:site_name" content="{{ config('app.name') }}" />
<meta property="og:title" content="{{ config('app.name') }}" />
