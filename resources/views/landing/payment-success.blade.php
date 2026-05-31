@extends('landing.layouts.app')

@section('title', 'Processing Payment')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4 bg-slate-50">
        <div class="max-w-md text-center">
            <!-- Processing Icon with Animation -->
            <div class="relative mb-8">
                <div
                    class="mx-auto flex h-24 w-24 animate-[scale_0.5s_ease-out] items-center justify-center rounded-full bg-blue-100">
                    <svg class="h-14 w-14 text-blue-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                        </path>
                    </svg>
                </div>
                <!-- Subtle pulse ring -->
                <div class="absolute inset-0 mx-auto h-24 w-24 animate-ping rounded-full bg-blue-400/20"
                    style="animation-duration: 2s"></div>
            </div>

            <!-- Text Content -->
            <h1 class="mb-3 text-3xl font-semibold text-slate-900">Processing Payment</h1>
            <p class="mb-8 text-slate-600">Please wait while we securely process your payment. This may take a few moments.
            </p>

            <!-- Login Button -->
            <a href="{{ route('home') }}"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">
                Go to Home Page
            </a>

            <!-- Additional Info -->
            <p class="mt-4 text-xs text-slate-500">
                You'll receive a confirmation email once your payment is complete
            </p>
        </div>
    </div>

@endsection

@push('preload')
    <style>
        @keyframes scale {
            0% {
                transform: scale(0);
                opacity: 0;
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Auto refresh every 3 seconds to check payment status
        setInterval(function() {
            window.location.reload();
        }, 3000);
    </script>
@endpush
