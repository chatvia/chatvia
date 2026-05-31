@extends('landing.layouts.app')

@section('title', 'Chatvia.ai - GDPR Compliant AI Chatbot Platform | EU Hosted')

@section('description', 'Build intelligent AI chatbots with ChatVia. 100% EU-hosted infrastructure. GDPR compliant with open-source models like Llama, DeepSeek, Qwen. Your data never leaves EU.')

@section('og_title', 'Chatvia.ai - GDPR Compliant AI Chatbot Platform')

@section('og_description', 'Transform your customer support with EU-hosted AI chatbots. 100% EU infrastructure. GDPR compliant with open-source AI (Llama, DeepSeek, Qwen, Gemma). Instant 24/7 support.')

@php
    $preloadImages = $landing['features']['slide']['tabs'] ?? [
        ['img' => 'images/chatvia/chat-widget.png'],
        ['img' => 'images/chatvia/gpt.png'],
        ['img' => 'images/chatvia/training.png'],
    ];
@endphp
@push('preload')
    {{-- Preload first 3 feature images for better performance --}}
    @foreach(array_slice($preloadImages, 0, 3) as $tab)
        <link rel="preload" as="image" href="{{ asset($tab['img']) }}">
    @endforeach
@endpush

@section('content')
    <div class="flex flex-col min-h-screen overflow-hidden">

        @include('landing.partials.header')

        <!-- Page content -->
        <main class="grow">

            @include('landing.partials.hero')

{{--            @include('landing.partials.trust-badges')--}}

{{--            @include('landing.partials.core-features')--}}

            @include('landing.partials.features-bar')

            @include('landing.partials.features')

            <!-- Why Chatbot Section -->
            @if(($landing['show-why-chatbot'] ?? true) === true)
                <section class="bg-white">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6">
                        <div class="py-16 md:py-24">

                            <!-- Section header -->
                            <div class="max-w-2xl mx-auto text-center pb-12 md:pb-16">
                                <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-slate-900 mb-4">
                                    Why you should have a chatbot for your business?
                                </h2>
                                <p class="text-lg md:text-xl text-slate-500">
                                    Having a chatbot can help your business grow, manage customer inquiries, and improve
                                    customer satisfaction.
                                </p>
                            </div>

                            <!-- Section content -->
                            <div
                                class="max-w-xl mx-auto md:max-w-none flex flex-col md:flex-row md:items-center md:space-x-8 lg:space-x-16 xl:space-x-18 space-y-8 space-y-reverse md:space-y-0">

                                <!-- Content -->
                                <div class="md:w-7/12 lg:w-1/2 order-1 md:order-none" data-aos="fade-right">
                                    <ul class="space-y-6">
                                        <li class="bg-slate-50 rounded-xl p-6">
                                            <div class="flex items-center mb-3">
                                                <div class="w-10 h-10 bg-rose-100 rounded-lg flex items-center justify-center mr-4">
                                                    <svg class="h-5 w-5 fill-current text-rose-500" viewBox="0 0 20 20">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                    </svg>
                                                </div>
                                                <div class="text-3xl font-display font-bold text-slate-900">56M+</div>
                                            </div>
                                            <div class="text-slate-600">
                                                Bank of America's "Erica" chatbot handles 56 million engagements
                                                monthly and resolves 98% of queries within 44 seconds
                                            </div>
                                        </li>
                                        <li class="bg-slate-50 rounded-xl p-6">
                                            <div class="flex items-center mb-3">
                                                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center mr-4">
                                                    <svg class="h-5 w-5 fill-current text-amber-500" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                                                    </svg>
                                                </div>
                                                <div class="text-3xl font-display font-bold text-slate-900">80%</div>
                                            </div>
                                            <div class="text-slate-600">
                                                AI now handles up to 80% of customer support inquiries autonomously,
                                                cutting complex case resolution time by 52%
                                            </div>
                                        </li>
                                        <li class="bg-slate-50 rounded-xl p-6">
                                            <div class="flex items-center mb-3">
                                                <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                                                    <svg class="h-5 w-5 fill-current text-indigo-500" viewBox="0 0 20 20">
                                                        <path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/>
                                                    </svg>
                                                </div>
                                                <div class="text-3xl font-display font-bold text-slate-900">62%</div>
                                            </div>
                                            <div class="text-slate-600">
                                                62% of consumers prefer using a chatbot than wait for a human agent,
                                                signaling strong preference for immediacy
                                            </div>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Image -->
                                <div class="md:w-5/12 lg:w-1/2" data-aos="fade-left">
                                    <img class="mx-auto md:max-w-none rounded-2xl shadow-lg" src="./images/target.png" width="540"
                                         height="540"
                                         alt="Target audience for AI chatbots - businesses seeking customer support automation"/>
                                </div>

                            </div>

                        </div>
                    </div>
                </section>
            @endif

            @include('landing.partials.comparison')

            @include('landing.partials.pricing')

            @include('landing.partials.faqs')

            @include('landing.partials.cta')

        </main>

        @include('landing.partials.footer')

    </div>

    @include('landing.partials.video-modal')
@endsection
