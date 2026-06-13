@extends('landing.layouts.app')

@section('title', $title . ' - ' . config('app.name'))
@section('description', 'How Chatvia protects your data: EU hosting, encryption, access control, GDPR, and open-weight AI models. A plain-language summary of our security and privacy posture.')

@push('preload')
    <style>
        .legal-prose { color: #475569; line-height: 1.7; }

        .legal-prose h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin: 2.5rem 0 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .legal-prose h2:first-of-type { border-top: 0; padding-top: 0; margin-top: 1rem; }

        .legal-prose h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e293b;
            margin: 1.75rem 0 .5rem;
        }

        .legal-prose p { margin: .85rem 0; }
        .legal-prose ul { margin: .6rem 0 1rem; padding-left: 1.25rem; list-style: disc; }
        .legal-prose li { margin: .4rem 0; }
        .legal-prose a { color: #2563eb; text-decoration: underline; overflow-wrap: anywhere; }
        .legal-prose strong { color: #0f172a; font-weight: 600; }

        .legal-prose blockquote {
            margin: 1rem 0;
            padding: .75rem 1rem;
            border-left: 3px solid #93c5fd;
            background: #eff6ff;
            border-radius: 0 .375rem .375rem 0;
            color: #1e3a8a;
        }

        .legal-prose table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: .9rem; }
        .legal-prose th, .legal-prose td { border: 1px solid #e2e8f0; padding: .5rem .75rem; text-align: left; vertical-align: top; }
        .legal-prose th { background: #f8fafc; font-weight: 600; }
    </style>
@endpush

@section('content')
    <div class="flex flex-col min-h-screen overflow-hidden">

        @include('landing.partials.header')

        <main class="grow pt-28 pb-20">
            <div class="max-w-4xl mx-auto px-5 sm:px-6">
                <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 mb-2">{{ $title }}</h1>
                @if (! empty($effectiveDate))
                    <p class="text-slate-500 text-sm mb-6">Last updated: {{ $effectiveDate }}</p>
                @endif

                <div class="mb-10">
                    <a href="{{ route('dpa') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                        Read the Data Processing Agreement (DPA)
                        <svg class="w-4 h-4 rtl:-scale-x-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                </div>

                <div class="legal-prose">
                    {{-- Trusted, version-controlled trust-center doc, rendered from markdown. --}}
                    {!! $html !!}
                </div>
            </div>
        </main>

        @include('landing.partials.footer')

    </div>
@endsection
