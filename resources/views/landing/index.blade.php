<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>ChatVIA - Home</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    @vite(['resources/css/landing.css'])

    <script type="text/javascript">window.$crisp=[];window.CRISP_WEBSITE_ID="832171b1-3e2c-434c-baa9-3f9b189ba015";(function(){d=document;s=d.createElement("script");s.src="https://client.crisp.chat/l.js";s.async=1;d.getElementsByTagName("head")[0].appendChild(s);})();</script>

    <link rel="icon" type="image/png" href="{{ asset('images/favicons/favicon-96x96.png') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicons/favicon.svg') }}" />
    <link rel="shortcut icon" href="{{ asset('images/favicons/favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicons/apple-touch-icon.png') }}" />
    <meta name="apple-mobile-web-app-title" content="ChatVia.ai" />
    <link rel="manifest" href="{{ asset('images/favicons/site.webmanifest') }}" />

    <!-- Social media share -->
    <meta property="og:title" content="Create AI Agents similar to ChatGPT but with your own data">
    <meta property="og:site_name" content="ChatVia.ai">
    <meta property="og:url" content="https://chatvia.ai">
    <meta property="og:image" content="https://chatvia.ai/images/chatvia.png">
    <meta property="og:description" content="Create AI Agents similar to ChatGPT but with your own data">
    <meta property="og:type" content="">

    <meta name="twitter:card" content="Create AI Agents similar to ChatGPT but with your own data"/>
    <meta name="twitter:site" content="@ahmadmayahi"/>
    <meta name="twitter:creator" content="@ahmadmayahi"/>


</head>

<body class="font-inter antialiased bg-white text-zinc-900 tracking-tight scroll-smooth overflow-x-hidden">

<!-- Page wrapper -->
<div class="flex flex-col min-h-screen overflow-hidden supports-[overflow:clip]:overflow-clip">

    <!-- Site header -->
    <header class="absolute top-2 md:top-6 w-full z-30">
        <div class="px-4 sm:px-6">
            <div class="max-w-3xl mx-auto">
                <div class="flex items-center justify-between h-14 border border-transparent [background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] rounded-lg px-3">

                    <!-- Site branding -->
                    <div class="shrink-0 mr-4">
                        <!-- Logo -->
                        <a class="flex items-center justify-center w-8 h-8"
                           href="{{ route('home') }}">
                            <img src="{{ asset('images/logo.svg') }}" width="24" height="24" alt="Logo">
                        </a>
                    </div>

                    <!-- Mobile menu button -->
                    <div class="md:hidden" x-data="{ mobileMenuOpen: false }">
                        <button
                                class="text-zinc-500 hover:text-zinc-900 p-2"
                                @click="mobileMenuOpen = !mobileMenuOpen"
                                :aria-expanded="mobileMenuOpen"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>

                        <!-- Mobile menu dropdown -->
                        <div
                                x-show="mobileMenuOpen"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute top-full left-3 right-3 mt-2 bg-white rounded-lg shadow-lg border border-zinc-200 py-2 z-50"
                                @click.away="mobileMenuOpen = false"
                        >
                            <a href="#features" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50"
                               @click="mobileMenuOpen = false; document.getElementById('features').scrollIntoView({behavior: 'smooth', block: 'start'})">Features</a>
                            <a href="#pricing" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50"
                               @click="mobileMenuOpen = false; document.getElementById('pricing').scrollIntoView({behavior: 'smooth', block: 'start'})">Pricing</a>
                            <a href="#faqs" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50"
                               @click="mobileMenuOpen = false; document.getElementById('faqs').scrollIntoView({behavior: 'smooth', block: 'start'})">FAQs</a>
                            <a href="#support" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50"
                               @click="mobileMenuOpen = false; document.getElementById('support').scrollIntoView({behavior: 'smooth', block: 'start'})">Support</a>
                            <div class="border-t border-zinc-200 my-2"></div>
                            @auth
                                <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50"
                                   @click="mobileMenuOpen = false">Dashboard</a>

                            @else
                                <a href="{{ route('register') }}"
                                   class="block px-4 py-2 text-sm text-white bg-zinc-900 hover:bg-zinc-800 mx-4 rounded-md text-center"
                                   @click="mobileMenuOpen = false">Get Started</a>
                            @endauth

                        </div>
                    </div>

                    <!-- Desktop navigation -->
                    <nav class="hidden md:flex grow">

                        <!-- Desktop menu items -->
                        <ul class="flex grow justify-start flex-wrap items-center ml-8">
                            <li>
                                <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                                   href="#features" onclick="document.getElementById('features').scrollIntoView({behavior: 'smooth', block: 'start'})">Features</a>
                            </li>
                            <li>
                                <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                                   href="#pricing" onclick="document.getElementById('pricing').scrollIntoView({behavior: 'smooth', block: 'start'})">Pricing</a>
                            </li>
                            <li>
                                <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                                   href="#faqs" onclick="document.getElementById('faqs').scrollIntoView({behavior: 'smooth', block: 'start'})">FAQs</a>
                            </li>
                            <li>
                                <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                                   href="#support" onclick="document.getElementById('support').scrollIntoView({behavior: 'smooth', block: 'start'})">Support</a>
                            </li>
                        </ul>

                        <!-- Desktop sign in links -->
                        <ul class="flex justify-end flex-wrap items-center">
                            @auth
                                <li>
                                    <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                                       href="{{ route('dashboard') }}">Dashboard</a>
                                </li>

                                <li class="ml-1">
                                    <form method="post" action="{{ route('logout') }}">
                                        @csrf
                                    <button class="btn-sm cursor-pointer text-zinc-100 bg-zinc-900 hover:bg-zinc-800 w-full shadow-sm"
                                       >Logout</button>
                                    </form>
                                </li>
                            @else
                                <li>
                                    <a class="text-sm font-medium text-zinc-500 hover:text-zinc-900 px-3 lg:px-5 py-2 flex items-center transition"
                                       href="{{ route('login') }}">Log in</a>
                                </li>
                                <li class="ml-1">
                                    <a class="btn-sm text-zinc-100 bg-zinc-900 hover:bg-zinc-800 w-full shadow-sm"
                                       href="{{ route('register') }}">Get Started</a>
                                </li>

                            @endauth
                        </ul>

                    </nav>

                </div>
            </div>
        </div>
    </header>

    <!-- Page content -->
    <main class="grow">

        <!-- Hero -->
        <section
                class="relative before:absolute before:inset-0 before:h-80 before:pointer-events-none before:bg-linear-to-b before:from-zinc-100 before:-z-10">
            <div class="pt-32 pb-12 md:pt-40 md:pb-20">
                <!-- Section content -->
                <div class="px-4 sm:px-6">
                    <div class="max-w-4xl mx-auto">
                        <div class="text-center">
                            <h1 class="font-inter-tight text-4xl md:text-5xl font-extrabold bg-clip-text text-transparent bg-linear-to-r from-zinc-500 via-zinc-900 to-zinc-900 pb-4 mb-6">
                                AI Agents Trained On
                                <em class="italic relative inline-flex justify-center items-center text-zinc-900">
                                    Your Data
                                    <svg class="absolute fill-zinc-300 w-[calc(100%+1rem)] -z-10 max-w-full"
                                         xmlns="http://www.w3.org/2000/svg" width="223" height="62" viewBox="0 0 223 62"
                                         aria-hidden="true" preserveAspectRatio="none">
                                        <path d="M45.654 53.62c17.666 3.154 35.622 4.512 53.558 4.837 17.94.288 35.91-.468 53.702-2.54 8.89-1.062 17.742-2.442 26.455-4.352 8.684-1.945 17.338-4.3 25.303-7.905 3.94-1.81 7.79-3.962 10.634-6.777 1.38-1.41 2.424-2.994 2.758-4.561.358-1.563-.078-3.143-1.046-4.677-.986-1.524-2.43-2.96-4.114-4.175a37.926 37.926 0 0 0-5.422-3.32c-3.84-1.977-7.958-3.563-12.156-4.933-8.42-2.707-17.148-4.653-25.95-6.145-8.802-1.52-17.702-2.56-26.622-3.333-17.852-1.49-35.826-1.776-53.739-.978-8.953.433-17.898 1.125-26.79 2.22-8.887 1.095-17.738 2.541-26.428 4.616-4.342 1.037-8.648 2.226-12.853 3.676-4.197 1.455-8.314 3.16-12.104 5.363-1.862 1.13-3.706 2.333-5.218 3.829-1.52 1.47-2.79 3.193-3.285 5.113-.528 1.912-.127 3.965.951 5.743 1.07 1.785 2.632 3.335 4.348 4.68 2.135 1.652 3.2 2.672 2.986 3.083-.18.362-1.674.114-4.08-1.638-1.863-1.387-3.63-3.014-4.95-5.09C.94 35.316.424 34.148.171 32.89c-.275-1.253-.198-2.579.069-3.822.588-2.515 2.098-4.582 3.76-6.276 1.673-1.724 3.612-3.053 5.57-4.303 3.96-2.426 8.177-4.278 12.457-5.868 4.287-1.584 8.654-2.89 13.054-4.036 8.801-2.292 17.74-3.925 26.716-5.19C70.777 2.131 79.805 1.286 88.846.723c18.087-1.065 36.236-.974 54.325.397 9.041.717 18.07 1.714 27.042 3.225 8.972 1.485 17.895 3.444 26.649 6.253 4.37 1.426 8.697 3.083 12.878 5.243a42.11 42.11 0 0 1 6.094 3.762c1.954 1.44 3.823 3.2 5.283 5.485a12.515 12.515 0 0 1 1.63 3.88c.164.706.184 1.463.253 2.193-.063.73-.094 1.485-.247 2.195-.652 2.886-2.325 5.141-4.09 6.934-3.635 3.533-7.853 5.751-12.083 7.688-8.519 3.778-17.394 6.09-26.296 7.998-8.917 1.86-17.913 3.152-26.928 4.104-18.039 1.851-36.17 2.295-54.239 1.622-18.062-.713-36.112-2.535-53.824-6.23-5.941-1.31-5.217-2.91.361-1.852"></path>
                                    </svg>
                                </em>
                            </h1>
                            <div class="text-xl md:text-2xl text-zinc-600 font-medium mb-6 leading-relaxed">
                                Create AI Agents similar to ChatGPT but with your own data<br>
                                Upload PDFs, Word docs, and any files
                            </div>


                                <div class="flex gap-3 justify-center mt-4 mb-2">
                                    <div class="inline-flex items-center gap-2 text-sm text-zinc-500 bg-zinc-100 px-4 py-2 rounded-full">
                                        <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                        GDPR compliant
                                    </div>
                                    <div class="inline-flex items-center gap-2 text-sm text-zinc-500 bg-zinc-100 px-4 py-2 rounded-full">
                                        <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                        Hosted in EU
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Video Section - Full Width -->
                    <div class="px-4 sm:px-6">
                        <div class="relative max-w-5xl mx-auto" x-data="{ showModal: false }">
                            <!-- Video Thumbnail Container -->
                            <div class="relative cursor-pointer group" @click="showModal = true">

                                <!-- Main Image -->
                                <img
                                        src="{{ asset('images/landing/intro.png') }}"
                                        alt="Video Preview"
                                        class="w-full rounded-lg shadow-2xl"
                                >

                                <!-- Play Button Overlay - Centered on top -->
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="w-24 h-24 bg-white bg-opacity-90 backdrop-blur-sm rounded-full shadow-xl flex items-center justify-center group-hover:scale-110 group-hover:bg-opacity-100 transition-all duration-300">
                                        <svg class="w-10 h-10 text-zinc-900 ml-1" fill="currentColor"
                                             viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Hover Overlay -->
                                <div class="absolute inset-0 rounded-lg transition-all duration-300"></div>
                            </div>

                            <!-- Video Modal -->
                            <div
                                    x-show="showModal"
                                    x-transition:enter="transition ease-out duration-300"
                                    x-transition:enter-start="opacity-0"
                                    x-transition:enter-end="opacity-100"
                                    x-transition:leave="transition ease-in duration-200"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0"
                                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75"
                                    @click.self="showModal = false"
                                    @keydown.escape.window="showModal = false"
                                    style="display: none;"
                            >
                                <div class="relative max-w-4xl w-full bg-white rounded-lg shadow-2xl overflow-hidden"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-200"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95">

                                    <!-- Close Button -->
                                    <button
                                            @click="showModal = false"
                                            class="absolute top-4 right-4 z-10 w-8 h-8 bg-black bg-opacity-50 hover:bg-opacity-75 text-white rounded-full flex items-center justify-center transition-all duration-200"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>

                                    <!-- Video Player -->
                                    <video
                                            class="w-full aspect-video"
                                            controls
                                            autoplay
                                            preload="metadata"
                                            x-init="$watch('showModal', value => { if (!value) $el.pause(); $el.currentTime = 0; })"
                                    >
                                        <source src="./intro.mp4" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="relative bg-zinc-50">
            <div class="py-12 md:py-20">
                <div class="max-w-6xl mx-auto px-4 sm:px-6">
                    <div class="max-w-3xl mx-auto text-center pb-12">
                        <div class="inline-flex text-sm font-medium text-emerald-600 px-4 py-0.5 border border-transparent [background:linear-gradient(var(--color-emerald-50),var(--color-emerald-50))_padding-box,linear-gradient(120deg,var(--color-emerald-300),var(--color-emerald-100),var(--color-emerald-300))_border-box] rounded-full mb-4">
                            Your data stays private with RAG technology
                        </div>
                        <h2 class="font-inter-tight text-3xl md:text-4xl lg:text-5xl font-extrabold text-zinc-900 mb-4">
                            Powerful features for your AI agents
                        </h2>
                        <p class="text-lg text-zinc-500">Create custom AI agents that understand your data.</p>
                        <p class="text-lg text-zinc-500">
                            Train on your documents or use instruction-based agents for immediate deployment.
                        </p>
                    </div>
                    <div x-data="{ tab: '1' }">
                        <!-- Tabs buttons -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                            <button
                                    :class="tab !== '1' ? 'bg-zinc-100 opacity-60 hover:opacity-100 transition' : '[background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] shadow-xs rotate-1'"
                                    class="text-left px-4 py-5 border border-transparent rounded-sm"
                                    @click.prevent="tab = '1'"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <div class="font-inter-tight font-semibold text-zinc-900">Training Agents</div>
                                    <svg :class="tab !== '1' ? 'hidden' : ''" class="fill-zinc-400 shrink-0 ml-2"
                                         xmlns="http://www.w3.org/2000/svg" width="10" height="10">
                                        <path d="M8.667.186H2.675a.999.999 0 0 0 0 1.998h3.581L.971 7.469a.999.999 0 1 0 1.412 1.412l5.285-5.285v3.58a.999.999 0 1 0 1.998 0V1.186a.999.999 0 0 0-.999-.999Z"/>
                                    </svg>
                                </div>
                                <div class="text-sm text-zinc-500">Upload your documents and train AI agents on your
                                    specific data and knowledge.
                                </div>
                            </button>
                            <button
                                    :class="tab !== '2' ? 'bg-zinc-100 opacity-60 hover:opacity-100 transition' : '[background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] shadow-xs rotate-1'"
                                    class="text-left px-4 py-5 border border-transparent rounded-sm"
                                    @click.prevent="tab = '2'"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <div class="font-inter-tight font-semibold text-zinc-900">Instruction Agents</div>
                                    <svg :class="tab !== '2' ? 'hidden' : ''" class="fill-zinc-400 shrink-0 ml-2"
                                         xmlns="http://www.w3.org/2000/svg" width="10" height="10">
                                        <path d="M8.667.186H2.675a.999.999 0 0 0 0 1.998h3.581L.971 7.469a.999.999 0 1 0 1.412 1.412l5.285-5.285v3.58a.999.999 0 1 0 1.998 0V1.186a.999.999 0 0 0-.999-.999Z"/>
                                    </svg>
                                </div>
                                <div class="text-sm text-zinc-500">Create agents with custom instructions without
                                    training on documents.
                                </div>
                            </button>
                            <button
                                    :class="tab !== '3' ? 'bg-zinc-100 opacity-60 hover:opacity-100 transition' : '[background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] shadow-xs rotate-1'"
                                    class="text-left px-4 py-5 border border-transparent rounded-sm"
                                    @click.prevent="tab = '3'"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <div class="font-inter-tight font-semibold text-zinc-900">Models</div>
                                    <svg :class="tab !== '3' ? 'hidden' : ''" class="fill-zinc-400 shrink-0 ml-2"
                                         xmlns="http://www.w3.org/2000/svg" width="10" height="10">
                                        <path d="M8.667.186H2.675a.999.999 0 0 0 0 1.998h3.581L.971 7.469a.999.999 0 1 0 1.412 1.412l5.285-5.285v3.58a.999.999 0 1 0 1.998 0V1.186a.999.999 0 0 0-.999-.999Z"/>
                                    </svg>
                                </div>
                                <div class="text-sm text-zinc-500">
                                    State-of-the-art AI models for intelligent, accurate responses.
                                </div>
                            </button>
                            <button
                                    :class="tab !== '4' ? 'bg-zinc-100 opacity-60 hover:opacity-100 transition' : '[background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] shadow-xs rotate-1'"
                                    class="text-left px-4 py-5 border border-transparent rounded-sm"
                                    @click.prevent="tab = '4'"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <div class="font-inter-tight font-semibold text-zinc-900">Team Collaboration</div>
                                    <svg :class="tab !== '4' ? 'hidden' : ''" class="fill-zinc-400 shrink-0 ml-2"
                                         xmlns="http://www.w3.org/2000/svg" width="10" height="10">
                                        <path d="M8.667.186H2.675a.999.999 0 0 0 0 1.998h3.581L.971 7.469a.999.999 0 1 0 1.412 1.412l5.285-5.285v3.58a.999.999 0 1 0 1.998 0V1.186a.999.999 0 0 0-.999-.999Z"/>
                                    </svg>
                                </div>
                                <div class="text-sm text-zinc-500">Add team members, collaborate on agents, and share
                                    custom starter messages.
                                </div>
                            </button>
                        </div>
                        <!-- Tabs items -->
                        <div class="relative lg:max-w-none [mask-image:linear-gradient(white_0%,white_calc(100%-40px),_transparent_calc(100%-1px))] overflow-hidden">
                            <div class="relative flex flex-col pt-12 md:pt-20">
                                <!-- Item 1 -->
                                <div
                                        class="w-full text-center"
                                        x-show="tab === '1'"
                                        x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                        x-transition:enter-start="opacity-0 -translate-y-4"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 translate-y-4"
                                >
                                    <div class="inline-flex relative align-top">
                                        <img class="w-full max-w-5xl h-auto rounded-t-lg border border-transparent [background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] box-content shadow-2xl"
                                             src="{{ asset('images/landing/feature-01.png') }}" alt="Feature 01"/>
                                    </div>
                                </div>
                                <!-- Item 2 -->
                                <div
                                        class="w-full text-center"
                                        x-show="tab === '2'"
                                        x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                        x-transition:enter-start="opacity-0 -translate-y-4"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 translate-y-4"
                                >
                                    <div class="inline-flex relative align-top">
                                        <img class="w-full max-w-5xl h-auto rounded-t-lg border border-transparent [background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] box-content shadow-2xl"
                                             src="{{ asset('images/landing/feature-02.png') }}" alt="Feature 01"/>
                                    </div>
                                </div>
                                <!-- Item 3 -->
                                <div
                                        class="w-full text-center"
                                        x-show="tab === '3'"
                                        x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                        x-transition:enter-start="opacity-0 -translate-y-4"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 translate-y-4"
                                >
                                    <div class="inline-flex relative align-top">
                                        <img class="w-full max-w-5xl h-auto rounded-t-lg border border-transparent [background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] box-content shadow-2xl"
                                             src="{{ asset('images/landing/feature-03.png') }}" alt="Feature 01"/>
                                    </div>
                                </div>
                                <!-- Item 4 -->
                                <div
                                        class="w-full text-center"
                                        x-show="tab === '4'"
                                        x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                        x-transition:enter-start="opacity-0 -translate-y-4"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 translate-y-4"
                                >
                                    <div class="inline-flex relative align-top">
                                        <img class="w-full max-w-5xl h-auto rounded-t-lg border border-transparent [background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] box-content shadow-2xl"
                                             src="{{ asset('images/landing/feature-04.png') }}" alt="Feature 01"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features #2 -->
        <section>
            <div class="py-12 md:py-20">
                <div class="max-w-6xl mx-auto px-4 sm:px-6">
                    <div class="relative max-w-3xl mx-auto text-center mb-10">
                        <h2 class="font-inter-tight text-3xl md:text-4xl lg:text-5xl font-extrabold text-zinc-900 mb-4">
                            Everything you need for AI agents</h2>
                        <p class="text-lg text-zinc-500 leading-relaxed">Upload your documents, customize your agents,
                            and deploy them instantly.</p>
                        <p class="text-lg text-zinc-500 leading-relaxed">
                            Your data will never be trained, rather we'll use the RAG method to safely process your documents.
                        </p>
                    </div>

                    <div class="max-w-xs mx-auto sm:max-w-none grid sm:grid-cols-2 md:grid-cols-3 gap-8 sm:gap-4 lg:gap-8">
                        <article
                                class="flex flex-col border border-transparent [background:linear-gradient(var(--color-white),var(--color-zinc-50))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] rounded-lg">
                            <div class="flex flex-col p-6 pt-7 h-full">
                                <div class="flex items-center space-x-3 mb-4">
                                    <svg class="inline-flex fill-zinc-400" xmlns="http://www.w3.org/2000/svg" width="20"
                                         height="20">
                                        <path d="M8.974 16c-.3 0-.7-.2-.9-.5l-2.2-3.7-2.1 2.8c-.3.4-1 .5-1.4.2-.4-.3-.5-1-.2-1.4l3-4c.2-.3.5-.4.9-.4.3 0 .6.2.8.5l2 3.3 3.3-8.1c0-.4.4-.7.8-.7s.8.2.9.6l4 8c.2.5 0 1.1-.4 1.3-.5.2-1.1 0-1.3-.4l-3-6-3.2 7.9c-.2.4-.6.6-1 .6Z"/>
                                    </svg>
                                    <h3 class="font-inter-tight text-lg font-semibold text-zinc-900">
                                        Purpose-built for LLMs
                                    </h3>
                                </div>

                                <p class="text-base text-zinc-600 mb-6 leading-relaxed">
                                    Choose from industry-leading AI models including GPT, Claude, Gemini, and more.
                                    Switch between models instantly.
                                </p>

                                <!-- LLM Model Icons -->
                                <div class="grid grid-cols-4 gap-3 mb-6">
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/gpt.svg') }}" alt="OpenAI GPT" class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/claude.svg') }}" alt="Anthropic Claude"
                                             class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/gemini.svg') }}" alt="Google Gemini"
                                             class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/mistral.svg') }}" alt="Mistral AI"
                                             class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/cohere.svg') }}" alt="Cohere" class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/llama.svg') }}" alt="Meta LLaMA"
                                             class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/qwen.svg') }}" alt="Qwen" class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/deepseek.svg') }}" alt="DeepSeek"
                                             class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/grok.svg') }}" alt="Grok" class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/nova.svg') }}" alt="Nova" class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm border border-zinc-100 hover:shadow-md transition-shadow">
                                        <img src="{{ asset('images/llms/microsoft.svg') }}" alt="Microsoft"
                                             class="w-7 h-7">
                                    </div>
                                    <div class="flex items-center justify-center w-12 h-12 bg-zinc-100 rounded-lg border border-zinc-200 hover:bg-zinc-200 transition-colors">
                                        <span class="text-sm text-zinc-500 font-medium">+more</span>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <p class="text-sm text-zinc-500 font-medium">
                                        No vendor lock-in • Switch models anytime
                                    </p>
                                </div>
                            </div>

                        </article>

                        <article
                                class="flex flex-col border border-transparent [background:linear-gradient(var(--color-white),var(--color-zinc-50))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] rounded-lg">
                            <div class="flex flex-col p-6 pt-7 h-full">
                                <div class="flex items-center space-x-3 mb-4">
                                    <svg class="inline-flex fill-zinc-400" xmlns="http://www.w3.org/2000/svg" width="20"
                                         height="20">
                                        <path d="M16 2H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h8.667l3.733 2.8A1 1 0 0 0 18 17V4a2 2 0 0 0-2-2Zm0 13-2.4-1.8a1 1 0 0 0-.6-.2H4V4h12v11Z"/>
                                    </svg>
                                    <h3 class="font-inter-tight text-lg font-semibold text-zinc-900">Multiple file
                                        types</h3>
                                </div>

                                <p class="text-base text-zinc-600 mb-6 leading-relaxed">
                                    Support for all document formats. Upload PDFs, Word docs, spreadsheets, images,
                                    videos, and more. Your agents work with any file type.
                                </p>

                                <!-- File Type Icons -->
                                <div class="grid grid-cols-5 gap-4 mb-6">
                                    <div class="flex items-center justify-center">
                                        <img src="{{ asset('images/landing/file-types/pdf.svg') }}" alt="PDF">
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <img src="{{ asset('images/landing/file-types/doc.svg') }}" alt="Word Document">
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <img src="{{ asset('images/landing/file-types/xls.svg') }}" alt="Excel">
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <img src="{{ asset('images/landing/file-types/txt.svg') }}" alt="Text File">
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <img src="{{ asset('images/landing/file-types/csv.svg') }}" alt="CSV">
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <img src="{{ asset('images/landing/file-types/png.svg') }}" alt="PNG Image">
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <img src="{{ asset('images/landing/file-types/jpg.svg') }}" alt="JPEG Image">
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <img src="{{ asset('images/landing/file-types/mp4.svg') }}" alt="MP4 Video">
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <img src="{{ asset('images/landing/file-types/html.svg') }}" alt="HTML">
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <img src="{{ asset('images/landing/file-types/rtf.svg') }}" alt="HTML">
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <p class="text-sm text-zinc-500 font-medium">
                                        Automatic processing
                                    </p>
                                </div>
                            </div>
                        </article>
                        <article
                            class="flex flex-col border border-transparent [background:linear-gradient(var(--color-white),var(--color-zinc-50))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] rounded-lg">
                            <div class="flex flex-col p-6 pt-7 h-full">
                                <div class="flex items-center space-x-3 mb-4">
                                    <svg class="inline-flex fill-zinc-400" xmlns="http://www.w3.org/2000/svg" width="20"
                                         height="20">
                                        <path d="M9.3 11.7c-.4-.4-.4-1 0-1.4l7-7c.4-.4 1-.4 1.4 0 .4.4.4 1 0 1.4l-7 7c-.4.4-1 .4-1.4 0ZM9.3 17.7c-.4-.4-.4-1 0-1.4l7-7c.4-.4 1-.4 1.4 0 .4.4.4 1 0 1.4l-7 7c-.4.4-1 .4-1.4 0ZM2.3 12.7c-.4-.4-.4-1 0-1.4l7-7c.4-.4 1-.4 1.4 0 .4.4.4 1 0 1.4l-7 7c-.4.4-1 .4-1.4 0Z"/>
                                    </svg>
                                    <h3 class="font-inter-tight text-lg font-semibold text-zinc-900">
                                        Advanced Retrieval System
                                    </h3>
                                </div>

                                <p class="text-base text-zinc-600 mb-6 leading-relaxed">
                                    ChatVia uses a combination of vector databases, bm25, knowledge graphs, and machine learning to provide the best results.
                                </p>

                                <!-- Video Container -->
                                <div class="flex-1 flex items-center justify-center mb-6">
                                    <img src="{{ asset('images/landing/search.svg') }}" alt="Search" class="w-20" />
                                </div>

                                <div class="mt-auto">
                                    <p class="text-sm text-zinc-500 font-medium">
                                        Ready in minutes • No coding required
                                    </p>
                                </div>
                            </div>
                        </article>
                        <article
                                class="flex flex-col border border-transparent [background:linear-gradient(var(--color-white),var(--color-zinc-50))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] rounded-lg">
                            <div class="flex flex-col p-6 pt-7 h-full">
                                <div class="flex items-center space-x-3 mb-4">
                                    <svg class="inline-flex fill-zinc-400" xmlns="http://www.w3.org/2000/svg" width="20"
                                         height="20">
                                        <path d="M3 3h18v18H3V3zm2 2v14h14V5H5zm2 2h10v2H7V7zm0 4h10v2H7v-2zm0 4h6v2H7v-2z"/>
                                    </svg>
                                    <h3 class="font-inter-tight text-lg font-semibold text-zinc-900">Complex Layouts</h3>
                                </div>

                                <p class="text-base text-zinc-600 mb-6 leading-relaxed">
                                    Our advanced RAG system understands complex document layouts, extracts data from tables, and processes images with OCR.
                                </p>

                                <!-- Table Icon -->
                                <div class="flex-1 flex items-center justify-center mb-6">
                                    <div class="w-20 h-20 bg-white rounded-lg shadow-sm border border-zinc-100 flex items-center justify-center">
                                        <svg class="w-10 h-10 text-zinc-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M3 3h18v18H3V3zm2 2v14h14V5H5zm2 2h10v2H7V7zm0 4h10v2H7v-2zm0 4h6v2H7v-2z"/>
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <p class="text-sm text-zinc-500 font-medium">
                                        Advanced document processing
                                    </p>
                                </div>
                            </div>
                        </article>
                        <article
                                class="flex flex-col border border-transparent [background:linear-gradient(var(--color-white),var(--color-zinc-50))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] rounded-lg">
                            <div class="flex flex-col p-6 pt-7 h-full">
                                <div class="flex items-center space-x-3 mb-4">
                                    <svg class="inline-flex fill-zinc-400" xmlns="http://www.w3.org/2000/svg" width="20"
                                         height="20">
                                        <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                                    </svg>
                                    <h3 class="font-inter-tight text-lg font-semibold text-zinc-900">LLM Image Parsing</h3>
                                </div>

                                <p class="text-base text-zinc-600 mb-6 leading-relaxed">
                                    Advanced AI models that can see, understand, and analyze images with human-like comprehension. Extract text, understand diagrams, and process visual content.
                                </p>

                                <!-- Image Analysis Icon -->
                                <div class="flex-1 flex items-center justify-center mb-6">
                                    <div class="w-20 h-20 bg-white rounded-lg shadow-sm border border-zinc-100 flex items-center justify-center">
                                        <svg class="w-10 h-10 text-zinc-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <p class="text-sm text-zinc-500 font-medium">
                                        Visual content analysis • OCR • Multi-modal AI
                                    </p>
                                </div>
                            </div>
                        </article>
                        <article
                                class="flex flex-col border border-transparent [background:linear-gradient(var(--color-white),var(--color-zinc-50))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] rounded-lg">
                            <div class="flex flex-col p-6 pt-7 h-full">
                                <div class="flex items-center space-x-3 mb-4">
                                    <svg class="inline-flex fill-zinc-400" xmlns="http://www.w3.org/2000/svg" width="20"
                                         height="20">
                                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                                    </svg>
                                    <h3 class="font-inter-tight text-lg font-semibold text-zinc-900">Enterprise-grade data security</h3>
                                </div>

                                <p class="text-base text-zinc-600 mb-6 leading-relaxed">
                                    Your data is protected with enterprise-grade security measures. GDPR compliant, encrypted storage, and secure access controls keep your information safe.
                                </p>

                                <!-- Security Icon -->
                                <div class="flex-1 flex items-center justify-center mb-6">
                                    <div class="w-20 h-20 bg-white rounded-lg shadow-sm border border-zinc-100 flex items-center justify-center">
                                        <svg class="w-10 h-10 text-zinc-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <p class="text-sm text-zinc-500 font-medium">
                                        GDPR compliant • Encrypted • Secure access
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features #3 -->
        <section
                class="relative bg-zinc-800 after:absolute after:top-0 after:right-0 after:h-full after:w-96 after:pointer-events-none after:bg-linear-to-l after:from-zinc-800 max-lg:after:hidden overflow-hidden">
            <div class="py-12 md:py-20">

                <!-- Carousel -->
                <div class="max-w-xl lg:max-w-6xl mx-auto px-4 sm:px-6">
                    <div class="lg:flex space-y-12 lg:space-y-0 lg:space-x-12 xl:space-x-24" x-data="{ tab: '1' }">

                        <!-- Content -->
                        <div class="lg:max-w-none lg:min-w-[524px]">
                            <div class="mb-8">
                                <div class="inline-flex text-sm font-medium text-zinc-400 px-4 py-0.5 border border-transparent [background:linear-gradient(var(--color-zinc-800),var(--color-zinc-800))_padding-box,linear-gradient(120deg,var(--color-zinc-700),--theme(--color-zinc-700/0),var(--color-zinc-700))_border-box] rounded-full mb-4">
                                    Your Private ChatGPT
                                </div>
                                <h3 class="font-inter-tight text-3xl font-bold text-zinc-200 mb-4">AI that knows your data very well</h3>
                                <p class="text-lg text-zinc-500">
                                    Deploy your own ChatGPT-style AI trained on your own data. From customer support to
                                    internal knowledge.
                                </p>
                            </div>
                            <!-- Tabs buttons -->
                            <div class="mb-8 md:mb-0 space-y-2">
                                <button
                                        :class="tab !== '1' ? '' : '[background:linear-gradient(#2E2E32,#2E2E32)_padding-box,linear-gradient(120deg,var(--color-zinc-700),--theme(--color-zinc-700/0),var(--color-zinc-700))_border-box]'"
                                        class="text-left flex items-center px-6 py-4 rounded-sm border border-transparent"
                                        @click.prevent="tab = '1'"
                                >
                                    <svg class="shrink-0 fill-zinc-400 mr-3" xmlns="http://www.w3.org/2000/svg"
                                         width="24" height="24" viewBox="0 0 24 24">
                                        <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                                    </svg>
                                    <div>
                                        <div class="font-inter-tight text-lg font-semibold text-zinc-200 mb-1">
                                            Familiar ChatGPT experience
                                        </div>
                                        <div class="text-zinc-500">Clean, intuitive interface that feels instantly
                                            familiar. Your team will be productive from day one.
                                        </div>
                                    </div>
                                </button>
                                <button
                                        :class="tab !== '2' ? '' : '[background:linear-gradient(#2E2E32,#2E2E32)_padding-box,linear-gradient(120deg,var(--color-zinc-700),--theme(--color-zinc-700/0),var(--color-zinc-700))_border-box]'"
                                        class="text-left flex items-center px-6 py-4 rounded-sm border border-transparent"
                                        @click.prevent="tab = '2'"
                                >
                                    <svg class="shrink-0 fill-zinc-400 mr-3" xmlns="http://www.w3.org/2000/svg"
                                         width="24" height="24" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                    <div>
                                        <div class="font-inter-tight text-lg font-semibold text-zinc-200 mb-1">Complete
                                            customization
                                        </div>
                                        <div class="text-zinc-500">Brand it your way. Customize logos, colors, models,
                                            and instructions to match your business perfectly.
                                        </div>
                                    </div>
                                </button>
                                <button
                                        :class="tab !== '3' ? '' : '[background:linear-gradient(#2E2E32,#2E2E32)_padding-box,linear-gradient(120deg,var(--color-zinc-700),--theme(--color-zinc-700/0),var(--color-zinc-700))_border-box]'"
                                        class="text-left flex items-center px-6 py-4 rounded-sm border border-transparent"
                                        @click.prevent="tab = '3'"
                                >
                                    <svg class="shrink-0 fill-zinc-400 mr-3" xmlns="http://www.w3.org/2000/svg"
                                         width="24" height="24" viewBox="0 0 24 24">
                                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                                    </svg>
                                    <div>
                                        <div class="font-inter-tight text-lg font-semibold text-zinc-200 mb-1">
                                            Enterprise-grade security
                                        </div>
                                        <div class="text-zinc-500">Control access with granular permissions. Keep your
                                            data secure with user management and authentication.
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Tabs items -->
                        <div class="relative lg:max-w-none">
                            <div class="relative flex flex-col">
                                <!-- Item 1 -->
                                <div
                                        class="w-full"
                                        x-show="tab === '1'"
                                        x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                        x-transition:enter-start="opacity-0 translate-x-8"
                                        x-transition:enter-end="opacity-100 translate-x-0"
                                        x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                        x-transition:leave-start="opacity-100 translate-x-0"
                                        x-transition:leave-end="opacity-0 -translate-x-8"
                                >
                                    <div>
                                        <img class="lg:max-w-none mx-auto rounded-lg shadow-2xl"
                                             src="{{ asset('images/landing/carousel-illustration-01.png') }}"
                                             width="800" height="620" alt="Carousel 01"/>
                                    </div>
                                </div>
                                <!-- Item 2 -->
                                <div
                                        class="w-full"
                                        x-show="tab === '2'"
                                        x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                        x-transition:enter-start="opacity-0 translate-x-8"
                                        x-transition:enter-end="opacity-100 translate-x-0"
                                        x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                        x-transition:leave-start="opacity-100 translate-x-0"
                                        x-transition:leave-end="opacity-0 -translate-x-8"
                                >
                                    <div>
                                        <img class="lg:max-w-none mx-auto rounded-lg shadow-2xl"
                                             src="{{ asset('images/landing/carousel-illustration-02.png') }}"
                                             width="800" height="620" alt="Carousel 02"/>
                                    </div>
                                </div>
                                <!-- Item 3 -->
                                <div
                                        class="w-full"
                                        x-show="tab === '3'"
                                        x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                        x-transition:enter-start="opacity-0 translate-x-8"
                                        x-transition:enter-end="opacity-100 translate-x-0"
                                        x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                        x-transition:leave-start="opacity-100 translate-x-0"
                                        x-transition:leave-end="opacity-0 -translate-x-8"
                                >
                                    <div>
                                        <img class="lg:max-w-none mx-auto rounded-lg shadow-2xl"
                                             src="{{ asset('images/landing/carousel-illustration-03.png') }}"
                                             width="800" height="620" alt="Carousel 03"/>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>


            </div>
        </section>

        <!-- Pricing -->
        <section id="pricing">
            <div class="py-12">
                <div class="max-w-6xl mx-auto px-4 sm:px-6">
                    <div class="relative max-w-3xl mx-auto text-center pb-12">
                        <h2 class="font-inter-tight text-3xl md:text-4xl lg:text-5xl font-extrabold text-zinc-900 mb-4">
                            Start your journey today</h2>
                        <p class="text-lg text-zinc-500">Start creating AI agents for free. Upgrade for
                            advanced features and team collaboration.</p>
                    </div>

                    <!-- Pricing Toggle and Cards Container -->
                    <div x-data="{ yearly: false }">
                        <!-- Pricing Toggle -->
                        <div class="flex justify-center mb-8">
                            <div class="flex items-center space-x-4 bg-zinc-100 rounded-lg p-1">
                                <button
                                    @click="yearly = false"
                                    :class="yearly ? 'text-zinc-500' : 'text-zinc-900 bg-white shadow-sm'"
                                    class="px-4 py-2 rounded-md text-sm font-medium transition-all duration-200">
                                    Monthly
                                </button>
                                <button
                                    @click="yearly = true"
                                    :class="yearly ? 'text-zinc-900 bg-white shadow-sm' : 'text-zinc-500'"
                                    class="px-4 py-2 rounded-md text-sm font-medium transition-all duration-200">
                                    Yearly
                                    <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        Save 20%
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Pricing tiers -->
                        <div>
                        <div class="max-w-sm mx-auto grid gap-6 lg:grid-cols-{{ count(config('billing.packages')) }} items-start lg:max-w-none">
                            @php
                                $packages = config('billing.packages');
                                $packageOrder = ['free', 'starter', 'standard', 'pro'];
                            @endphp

                            @foreach($packageOrder as $packageKey)
                                @if(isset($packages[$packageKey]))
                                    @php
                                        $package = $packages[$packageKey];
                                        $isPopular = $package['is_popular'] ?? false;
                                        $isDark = $isPopular;
                                        $textColor = $isDark ? 'text-white' : 'text-zinc-900';
                                        $bgClass = $isDark ? 'bg-zinc-800' : 'border border-transparent [background:linear-gradient(var(--color-zinc-50),var(--color-zinc-50))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box]';
                                        $features = $package['features']['monthly'] ?? [];
                                    @endphp

                                    <div class="h-full">
                                        <div class="relative flex flex-col h-full p-6 rounded-lg {{ $bgClass }}">
                                            @if($isPopular)
                                                <img class="absolute right-6 -top-5 mix-blend-exclusion"
                                                     src="{{ asset('images/landing/pricing-decoration.png') }}"
                                                     alt="Pricing decoration" width="76" height="74" aria-hidden="true">
                                            @endif

                                            <div class="mb-4">
                                                <div class="text-lg {{ $textColor }} font-semibold mb-1">{{ $package['name'] }}</div>
                                                <div class="font-inter-tight mb-2">
                                                    @if($package['monthly_price'] > 0)
                                                        <div class="inline-flex items-baseline">
                                                            <span class="{{ $textColor }} font-bold text-2xl">$</span>
                                                            <span class="{{ $textColor }} font-bold text-3xl" x-text="yearly ? '{{ number_format($package['yearly_price'] / 100, 2) }}' : '{{ number_format($package['monthly_price'] / 100, 2) }}'"></span>
                                                            <span class="{{ $textColor }} font-medium" x-text="yearly ? '/year' : '/mo'"></span>
                                                        </div>
                                                        <div x-show="yearly" class="{{ $textColor }} text-xs mt-1 opacity-75">(billed yearly)</div>
                                                    @else
                                                        <div class="inline-flex items-baseline">
                                                            <span class="{{ $textColor }} font-bold text-3xl">$0</span>
                                                            <span class="{{ $textColor }} font-medium">/mo</span>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="{{ $textColor }}">{{ $package['description'] }}</div>
                                                @if($package['monthly_price'] > 0)
                                                    <div x-show="yearly" class="text-sm text-emerald-600 font-medium mt-1">
                                                        Save ${{ number_format(($package['monthly_price'] * 12 - $package['yearly_price']) / 100, 2) }} per year
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="grow">
                                                <div class="text-sm {{ $textColor }} font-medium mb-4">Includes:</div>
                                                <ul class="text-zinc-600 dark:text-zinc-400 text-sm space-y-3 grow">
                                                    @foreach(array_slice($features, 0, 6) as $feature)
                                                        <li class="flex items-center">
                                                            <svg class="w-3 h-3 fill-emerald-500 mr-3 shrink-0" viewBox="0 0 12 12"
                                                                 xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M10.28 2.28L3.989 8.575 1.695 6.28A1 1 0 00.28 7.695l3 3a1 1 0 001.414 0l7-7A1 1 0 0010.28 2.28z"/>
                                                            </svg>
                                                            <span class="{{ $textColor }}">{{ $feature }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>

                                            <div class="mt-8">
                                                    <a class="btn text-zinc-100 bg-linear-to-r from-zinc-700 to-zinc-900 hover:from-zinc-900 hover:to-zinc-900 w-full shadow-sm"
                                                       href="{{ route('register') }}">
                                                        Get Started
                                                    </a>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


        </div>
    </div>
</section>


<!-- FAQs -->
<section id="faqs">
    <div class="py-12 md:py-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="max-w-2xl mx-auto mb-12">
                <div class="space-y-2">
                    @php
                        $faqs = [
                            [
                                'question' => 'What is ChatVIA?',
                                'answer' => 'ChatVIA is an AI platform that lets you create custom AI agents trained on your own data. Upload documents, PDFs, and files to create intelligent chatbots that understand your business knowledge and can answer questions about your content.',
                                'expanded' => true
                            ],
                            [
                                'question' => 'How does ChatVIA work?',
                                'answer' => 'Upload your documents (PDFs, Word docs, spreadsheets, etc.) and ChatVIA processes them using advanced AI. The system creates intelligent agents that can answer questions about your content, provide insights, and help users find information quickly.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'What types of files can I upload?',
                                'answer' => 'ChatVIA supports 44+ file types including PDFs, Word documents, Excel spreadsheets, PowerPoint presentations, images, videos, and more. Your AI agents can understand and work with any document format.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'How many AI agents can I create?',
                                'answer' => 'It depends on your plan. Free users get 1 instruction-based and 1 training-based agent. Paid plans offer more agents - Starter (2 each), Standard (5 each), and Pro (10 each). You can create unlimited agents on the Pro plan.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'What AI models does ChatVIA use?',
                                'answer' => 'ChatVIA supports multiple leading AI models including GPT, Claude, Gemini, Mistral, Cohere, LLaMA, and more. You can choose the best model for your needs and switch between them anytime.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'Is my data secure and private?',
                                'answer' => 'Yes, absolutely. Your data is encrypted and never shared with third parties. ChatVIA is GDPR compliant, hosted in the EU, and uses enterprise-grade security. Your documents and conversations remain private.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'Can I customize the AI agent\'s appearance?',
                                'answer' => 'Yes! You can customize your agent\'s name, instructions, and on Pro plans, you can add custom branding, logos, and even use your own domain for a white-label experience.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'What payment methods do you accept?',
                                'answer' => 'We accept all major credit cards (Visa, MasterCard, American Express) and PayPal. All payments are processed securely through Stripe. You can upgrade, downgrade, or cancel your plan anytime.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'Do you offer team collaboration features?',
                                'answer' => 'Yes! Standard and Pro plans include team collaboration. You can invite team members, share agents, collaborate on documents, and manage permissions. Perfect for businesses and organizations.',
                                'expanded' => false
                            ],
                            [
                                'question' => 'Can I use ChatVIA for my business?',
                                'answer' => 'Absolutely! ChatVIA is perfect for businesses of all sizes. Use it for customer support, internal knowledge bases, training materials, research, and more. Many companies use it to create intelligent assistants for their teams and customers.',
                                'expanded' => false
                            ]
                        ];
                    @endphp

                    @foreach($faqs as $index => $faq)
                        @php
                            $faqId = $index + 1;
                            $titleId = "faqs-title-{$faqId}";
                            $textId = "faqs-text-{$faqId}";
                            $expandedState = $faq['expanded'] ? 'true' : 'false';
                        @endphp

                        <!-- Accordion item -->
                        <div x-data="{ expanded: {{ $expandedState }} }" class="bg-zinc-100 rounded-sm">
                            <h2>
                                <button
                                        id="{{ $titleId }}"
                                        class="flex items-center justify-between w-full font-inter-tight text-left font-medium text-zinc-800 px-4 py-2.5"
                                        @click="expanded = !expanded"
                                        :aria-expanded="expanded"
                                        aria-controls="{{ $textId }}"
                                >
                                    <span>{{ $faq['question'] }}</span>
                                    <svg class="fill-zinc-400 shrink-0 ml-8" width="12" height="12" xmlns="http://www.w3.org/2000/svg">
                                        <rect y="5" width="12" height="2" rx="1"
                                              class="transform origin-center transition duration-200 ease-out"
                                              :class="{'rotate-180!': expanded}"/>
                                        <rect y="5" width="12" height="2" rx="1"
                                              class="transform origin-center rotate-90 transition duration-200 ease-out"
                                              :class="{'rotate-180!': expanded}"/>
                                    </svg>
                                </button>
                            </h2>
                            <div
                                    id="{{ $textId }}"
                                    role="region"
                                    aria-labelledby="{{ $titleId }}"
                                    class="grid text-sm text-zinc-500 overflow-hidden transition-all duration-300 ease-in-out"
                                    :class="expanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                            >
                                <div class="overflow-hidden">
                                    <p class="px-4 pb-3">
                                        {{ $faq['answer'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section id="support" class="bg-zinc-800">
    <div class="py-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="relative max-w-3xl mx-auto text-center">
                <h2 class="font-inter-tight text-3xl md:text-4xl lg:text-5xl font-extrabold text-zinc-200 mb-4">Create
                    your AI agents
                    <em class="relative not-italic inline-flex justify-center items-end">
                        for FREE
                        <svg class="absolute fill-zinc-300 w-[calc(100%+1rem)] -z-10 max-w-full" xmlns="http://www.w3.org/2000/svg"
                             width="120" height="10" viewBox="0 0 120 10" aria-hidden="true" preserveAspectRatio="none">
                            <path d="M118.273 6.09C79.243 4.558 40.297 5.459 1.305 9.034c-1.507.13-1.742-1.521-.199-1.81C39.81-.228 79.647-1.568 118.443 4.2c1.63.233 1.377 1.943-.17 1.89Z"/>
                        </svg>
                    </em>
                </h2>
                <p class="text-lg text-zinc-500 mb-8">
                    No credit card required
                </p>
                <div class="max-w-xs mx-auto sm:max-w-none sm:inline-flex sm:justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                    <div>
                        <a class="btn text-zinc-100 bg-zinc-900 hover:bg-zinc-800 w-full shadow-sm"
                           href="{{ route('register') }}">Start for FREE</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

</main>

<!-- Site footer -->
<footer class="bg-zinc-50 border-t border-zinc-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="py-8 md:py-12">
            <!-- Main footer content -->
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <!-- Logo and copyright -->
                <div class="flex flex-col items-center md:items-start space-y-2">
                    <a class="flex items-center justify-center w-12 h-12"
                       href="{{ route('home') }}">
                        <img src="{{ asset('images/logo.svg') }}"  alt="ChatVIA">
                    </a>
                    <div class="text-sm text-zinc-500">&copy; 2025 ChatVIA. All rights reserved.</div>
                </div>

                <!-- Essential links -->
                <div class="flex flex-wrap justify-center gap-6 text-sm">
                    <a class="text-zinc-500 hover:text-zinc-900 transition" href="{{ route('privacy') }}">Privacy Policy</a>
                    <a class="text-zinc-500 hover:text-zinc-900 transition" href="{{ route('terms') }}">Terms of Service</a>
                    <a class="text-zinc-500 hover:text-zinc-900 transition" href="{{ route('refund') }}">Refund Policy</a>
                </div>
            </div>
        </div>
    </div>
</footer>

</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>

</html>
