<link rel="icon" type="image/png" href="{{ asset('images/favicons/favicon-96x96.png') }}" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="{{ asset('images/favicons/favicon.svg') }}" />
<link rel="shortcut icon" href="{{ asset('images/favicons/favicon.ico') }}" />
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicons/apple-touch-icon.png') }}" />
<meta name="apple-mobile-web-app-title" content="ChatVia.ai" />
<link rel="manifest" href="{{ asset('images/favicons/site.webmanifest') }}" />

<!-- Preload critical resources -->
<link rel="preload" href="{{ asset('images/chatvia.png') }}" as="image" />
<link rel="preload" href="{{ asset('images/chatvia.png') }}" as="image" />
<link rel="dns-prefetch" href="//cdn.jsdelivr.net" />
<link rel="dns-prefetch" href="//fonts.googleapis.com" />

<!-- SEO Meta Tags -->
<meta name="description" content="@yield('description', 'Create AI Agents similar to ChatGPT but with your own data. Upload PDFs, Word docs, and any files. Launch in minutes with different data sources and deployment options.')" />
<meta name="keywords" content="@yield('keywords', 'AI agents, ChatGPT alternative, RAG, document processing, PDF analysis, AI chatbot, custom AI, knowledge base, document upload, AI training')" />
<meta name="author" content="ChatVIA" />
<meta name="robots" content="index, follow" />
<link rel="canonical" href="{{ url()->current() }}" />

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:title" content="@yield('og_title', 'ChatVIA - Create AI Agents similar to ChatGPT but with your own data')" />
<meta property="og:description" content="@yield('og_description', 'Create AI Agents similar to ChatGPT but with your own data. Upload PDFs, Word docs, and any files. Launch in minutes with different data sources and deployment options.')" />
<meta property="og:image" content="@yield('og_image', asset('images/chatvia.png'))" />
<meta property="og:image:width" content="1920" />
<meta property="og:image:height" content="1080" />
<meta property="og:site_name" content="ChatVIA.ai" />
<meta property="og:locale" content="en_US" />

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:url" content="{{ url()->current() }}" />
<meta name="twitter:title" content="@yield('twitter_title', 'ChatVIA - Create AI Agents similar to ChatGPT but with your own data')" />
<meta name="twitter:description" content="@yield('twitter_description', 'Create AI Agents similar to ChatGPT but with your own data. Upload PDFs, Word docs, and any files.')" />
<meta name="twitter:image" content="@yield('twitter_image', asset('images/chatvia.png'))" />
<meta name="twitter:site" content="@ahmadmayahi" />
<meta name="twitter:creator" content="@ahmadmayahi" />

<!-- LinkedIn -->
<meta property="og:image:alt" content="ChatVIA - Create AI Agents similar to ChatGPT but with your own data" />

<!-- Structured Data -->
@verbatim
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "ChatVIA",
  "description": "Create AI Agents similar to ChatGPT but with your own data. Upload PDFs, Word docs, and any files.",
  "url": "https://chatvia.ai",
  "applicationCategory": "BusinessApplication",
  "operatingSystem": "Web",
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "USD",
    "description": "Free trial available"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "ratingCount": "127",
    "bestRating": "5",
    "worstRating": "1"
  },
  "provider": {
    "@type": "Organization",
    "name": "ChatVIA",
    "url": "https://chatvia.ai"
  }
}
</script>
@endverbatim
