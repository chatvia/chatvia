@extends('landing.layouts.app')

@section('title', 'Chatvia.ai - Build & Train AI Agents on Your Data | EU Hosted')

@section('description', 'Chatvia is the B2B platform to build, train, and deploy AI agents on your own data. Connect MCP servers, call your APIs with custom functions, and let agents act in your tools. EU-hosted and GDPR compliant.')

@section('og_title', 'Chatvia.ai - Build & Train Your Own AI Agents')

@section('og_description', 'Give your team AI agents that know your data and act in your tools — MCP servers, custom functions, and tool calling. From idea to live agent in minutes. EU-hosted and GDPR compliant.')

@php
    $preloadImages = $landing['features']['slide']['tabs'] ?? [
        ['img' => 'images/chatvia/v2/widget.png'],
        ['img' => 'images/chatvia/v2/connectors.png'],
        ['img' => 'images/chatvia/v2/control-panel.png'],
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

            @include('landing.partials.integrations')

{{--            @include('landing.partials.trust-badges')--}}

{{--            @include('landing.partials.core-features')--}}

            @include('landing.partials.features-bar')

            @include('landing.partials.feature-cards')

            @include('landing.partials.features')

            @include('landing.partials.pricing')

            @include('landing.partials.faqs')

            @include('landing.partials.cta')

        </main>

        @include('landing.partials.footer')

    </div>
@endsection
