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

            <!-- Processing Message -->
            <div class="mb-6 rounded-xl bg-blue-50 p-6 border border-blue-200">
                <div class="flex items-center justify-center gap-3 text-blue-700">
                    <svg class="h-5 w-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">We're securely processing your payment...</span>
                </div>
            </div>

            <!-- Login Button -->
            <a href="{{ route('login') }}"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                    </path>
                </svg>
                Continue to Login
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
