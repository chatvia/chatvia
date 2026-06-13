@extends('landing.layouts.app')

@section('title', __('landing.meta.title'))

@section('description', __('landing.meta.description'))

@section('og_title', __('landing.meta.og_title'))

@section('og_description', __('landing.meta.og_description'))

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

{{--            @include('landing.partials.trust-badges')--}}

{{--            @include('landing.partials.comparison')--}}

            @include('landing.partials.integrations')

{{--            @include('landing.partials.core-features')--}}

            @include('landing.partials.features-bar')

            @include('landing.partials.channels')

            <!-- Faded hairline (with a soft sweeping light) separating the channels grid from the feature highlights -->
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <div class="relative h-px overflow-hidden bg-gradient-to-r from-transparent via-slate-300 to-transparent">
                    <div class="hairline-sweep absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-indigo-400/80 to-transparent"></div>
                </div>
            </div>

            @include('landing.partials.feature-cards')

            @include('landing.partials.features')

            @include('landing.partials.pricing')

            @include('landing.partials.faqs')

            @include('landing.partials.cta')

        </main>

        @include('landing.partials.footer')

    </div>
@endsection
