@extends('landing.layouts.app')

@section('title', 'Contact Us - ' . config('app.name'))

@section('description', 'Get in touch with the Chatvia team. Ask about the product, pricing, security, or anything else — we reply within one business day.')

@section('og_title', 'Contact Us - ' . config('app.name'))
@section('og_description', 'Get in touch with the Chatvia team. We reply within one business day.')

@section('content')
    <main class="flex">

        <!-- Content -->
        <div class="w-full md:w-1/2">

            <div class="min-h-screen h-full flex flex-col justify-center">

                <div class="px-5 sm:px-6 py-8">
                    <div class="w-full max-w-md mx-auto">

                        <!-- Site branding -->
                        <div class="mb-6">
                            <a class="inline-flex text-blue-600 transition duration-150 ease-in-out"
                               href="{{ route('home') }}" aria-label="{{ config('app.name') }}">
                                <img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }} Logo" class="w-14" />
                            </a>
                        </div>

                        @if (isset($submitted) && $submitted)
                            <!-- Success Message -->
                            <div class="text-center">
                                <div class="mb-8">
                                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <h1 class="h2 font-playfair-display text-slate-800 mb-4">Thanks for reaching out!</h1>
                                    <p class="text-slate-600 mb-6">
                                        We've received your message and will get back to you within one business day.
                                    </p>
                                    <a href="{{ route('home') }}" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
                                        ← Return to homepage
                                    </a>
                                </div>
                            </div>
                        @elseif (! ($contactEnabled ?? false))
                            <!-- No inbox configured (chatvia.contact_email) -->
                            <h1 class="h2 font-playfair-display text-slate-800 mb-3">Contact us</h1>
                            <p class="text-slate-600 mb-6">
                                The contact form is not available on this site right now.
                            </p>
                            <a href="{{ route('home') }}" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
                                ← Return to homepage
                            </a>
                        @else
                            <!-- Form -->
                            <h1 class="h2 font-playfair-display text-slate-800 mb-3">Contact us</h1>
                            <p class="text-slate-600 mb-10">
                                Questions about the product, pricing, or security? Send us a message and we'll reply within one business day.
                            </p>

                            <form action="{{ route('contact.submit') }}" method="POST">
                                @csrf
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="name">Name <span class="text-rose-500">*</span></label>
                                        <input id="name" name="name"
                                               class="form-input py-2 w-full @error('name') border-red-500 @enderror"
                                               type="text" value="{{ old('name') }}" required />
                                        @error('name')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="email">Email <span class="text-rose-500">*</span></label>
                                        <input id="email" name="email"
                                               class="form-input py-2 w-full @error('email') border-red-500 @enderror"
                                               type="email" value="{{ old('email') }}" required />
                                        @error('email')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="company">Company</label>
                                        <input id="company" name="company"
                                               class="form-input py-2 w-full @error('company') border-red-500 @enderror"
                                               type="text" value="{{ old('company') }}" />
                                        @error('company')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="message">Message <span class="text-rose-500">*</span></label>
                                        <textarea id="message" name="message" rows="5"
                                                  class="form-textarea py-2 w-full @error('message') border-red-500 @enderror"
                                                  placeholder="How can we help?" required>{{ old('message') }}</textarea>
                                        @error('message')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Turnstile Widget -->
                                    <div>
                                        <x-turnstile data-theme="light" data-language="en-US" />
                                        <x-turnstile.scripts />
                                        @error('cf-turnstile-response')
                                        <p class="error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <button class="btn-sm w-full text-sm text-white bg-blue-600 hover:bg-blue-700 group">
                                        Send message
                                    </button>
                                </div>
                            </form>
                        @endif

                    </div>
                </div>

            </div>

        </div>

        <!-- Right side -->
        <div class="relative hidden md:block md:w-1/2 bg-slate-900" aria-hidden="true">

            <!-- Bg image -->
            <div class="absolute inset-0">
                <img class="opacity-10 w-full h-full object-cover" src="{{ asset('images/request-demo-bg.jpg') }}"
                     width="760" height="900" alt="" />
            </div>

            <!-- Aside -->
            <div class="relative min-h-screen h-full flex flex-col justify-center">
                <div class="px-5 sm:px-6">
                    <div class="w-full max-w-lg mx-auto">
                        <h2 class="h3 md:text-4xl font-playfair-display text-slate-100 mb-5">Let's talk.</h2>
                        <p class="text-slate-300 leading-relaxed mb-8">
                            Whether you're evaluating Chatvia for your team or just have a question, we're happy to help.
                            Tell us what you need and a real person will get back to you.
                        </p>

                        @if ($companyEmail = \Support\Cms\Marketing\CompanyDetails::email())
                        <a href="mailto:{{ $companyEmail }}" class="inline-flex items-center gap-2 text-slate-100 font-medium hover:text-white">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            {{ $companyEmail }}
                        </a>
                        @endif

                        <div class="mt-10 space-y-3 text-sm text-slate-400">
                            <div class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                We reply within one business day.
                            </div>
                            <div class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                EU-based and GDPR compliant.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </main>
@endsection
