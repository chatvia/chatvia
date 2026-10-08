@php
    $brandName = (string) config('chatvia.brand.name');
    $brandUrl = config('chatvia.brand.website_url') ?: config('app.url');
    $twitterHandle = config('chatvia.brand.twitter_handle');
    $tagline = 'Create AI Agents similar to ChatGPT but with your own data';
@endphp
<link rel="icon" type="image/png" href="{{ asset('images/favicons/favicon-96x96.png') }}" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="{{ asset('images/favicons/favicon.svg') }}" />
<link rel="shortcut icon" href="{{ asset('images/favicons/favicon.ico') }}" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicons/apple-touch-icon.png') }}" />
<meta name="apple-mobile-web-app-title" content="{{ $brandName }}" />
<link rel="manifest" href="{{ asset('images/favicons/site.webmanifest') }}" />

<!-- Preload critical resources -->
<link rel="preload" href="{{ asset('images/chatvia.png') }}" as="image" />
<link rel="dns-prefetch" href="//cdn.jsdelivr.net" />

<!-- SEO Meta Tags -->
<meta name="description" content="@yield('description', 'Create AI Agents similar to ChatGPT but with your own data. Upload PDFs, Word docs, and any files. Launch in minutes with different data sources and deployment options.')" />
<meta name="keywords" content="@yield('keywords', 'AI agents, ChatGPT alternative, RAG, document processing, PDF analysis, AI chatbot, custom AI, knowledge base, document upload, AI training')" />
<meta name="author" content="{{ $brandName }}" />
<meta name="robots" content="index, follow" />
<link rel="canonical" href="{{ url()->current() }}" />

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:title" content="@yield('og_title', $brandName.' - '.$tagline)" />
<meta property="og:description" content="@yield('og_description', 'Create AI Agents similar to ChatGPT but with your own data. Upload PDFs, Word docs, and any files. Launch in minutes with different data sources and deployment options.')" />
<meta property="og:image" content="@yield('og_image', asset('images/chatvia.png'))" />
<meta property="og:image:width" content="1920" />
<meta property="og:image:height" content="1080" />
<meta property="og:site_name" content="{{ $brandName }}" />
<meta property="og:locale" content="en_US" />

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:url" content="{{ url()->current() }}" />
<meta name="twitter:title" content="@yield('twitter_title', $brandName.' - '.$tagline)" />
<meta name="twitter:description" content="@yield('twitter_description', 'Create AI Agents similar to ChatGPT but with your own data. Upload PDFs, Word docs, and any files.')" />
<meta name="twitter:image" content="@yield('twitter_image', asset('images/chatvia.png'))" />
@if (filled($twitterHandle))
<meta name="twitter:site" content="{{ $twitterHandle }}" />
<meta name="twitter:creator" content="{{ $twitterHandle }}" />
@endif

<!-- LinkedIn -->
<meta property="og:image:alt" content="{{ $brandName }} - {{ $tagline }}" />

<!-- Structured Data -->
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => $brandName,
    'description' => 'Create AI Agents similar to ChatGPT but with your own data. Upload PDFs, Word docs, and any files.',
    'url' => $brandUrl,
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Web',
    'provider' => [
        '@type' => 'Organization',
        'name' => config('chatvia.company.name') ?: $brandName,
        'url' => $brandUrl,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
</script>
