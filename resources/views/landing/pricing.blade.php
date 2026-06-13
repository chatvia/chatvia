@extends('landing.layouts.app')

@section('title', __('landing.pricing.heading') . ' - ' . config('app.name'))

@section('content')
    <div class="flex flex-col min-h-screen overflow-hidden">

        @include('landing.partials.header')

        <!-- Page content -->
        <main class="grow mt-10">
            @include('landing.partials.pricing')

            {{-- Shared, translated FAQ partial (same as the homepage) — no duplicated copy. --}}
            @include('landing.partials.faqs')
        </main>
        @include('landing.partials.footer')
    </div>
@endsection
