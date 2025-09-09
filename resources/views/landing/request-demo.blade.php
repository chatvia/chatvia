@extends('landing.layouts.app')

@section('content')
    <main class="flex">

        <!-- Content -->
        <div class="w-full md:w-1/2">

            <div class="min-h-screen h-full flex flex-col justify-center">

                <div class="px-5 sm:px-6 py-8">
                    <div class="w-full max-w-md mx-auto">

                        <!-- Site branding -->
                        <div class="mb-6">
                            <!-- Logo -->
                            <a class="inline-flex text-blue-600 transition duration-150 ease-in-out" href="index.html"
                               aria-label="Cruip">
                                <img src="{{  asset('images/logo.png') }}" alt="Logo" class="w-14"/>
                            </a>
                        </div>

                        @if(isset($submitted) && $submitted)
                            <!-- Success Message -->
                            <div class="text-center">
                                <div class="mb-8">
                                    <div
                                        class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <h1 class="h2 font-playfair-display text-slate-800 mb-4">Thank you for your
                                        interest!</h1>
                                    <p class="text-slate-600 mb-6">Your demo request has been successfully submitted.
                                        Our team will review your information and get back to you within 24 hours to
                                        schedule your personalized demonstration.</p>

                                    <a href="/"
                                       class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
                                        ← Return to homepage
                                    </a>
                                </div>
                            </div>
                        @else
                            <!-- Form -->
                            <h1 class="h2 font-playfair-display text-slate-800 mb-12">Request your demo</h1>

                            <form action="{{  route('demo') }}" method="POST">
                                @csrf
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="name">Name <span
                                                class="text-rose-500">*</span></label>
                                        <input id="name" name="name"
                                               class="form-input py-2 w-full @error('name') border-red-500 @enderror"
                                               type="text" value="{{ old('name') }}" required/>
                                        @error('name')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="email">Email <span
                                                class="text-rose-500">*</span></label>
                                        <input id="email" name="email"
                                               class="form-input py-2 w-full @error('email') border-red-500 @enderror"
                                               type="email" value="{{ old('email') }}" required/>
                                        @error('email')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="website">Website <span
                                                class="text-rose-500">*</span></label>
                                        <input id="website" name="website"
                                               class="form-input py-2 w-full @error('website') border-red-500 @enderror"
                                               type="url" placeholder="https://example.com" value="{{ old('website') }}"
                                               required/>
                                        @error('website')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-4 sm:flex sm:space-y-0 sm:space-x-4">
                                        <div class="sm:w-1/2">
                                            <label class="block text-sm font-medium mb-1" for="city">City <span
                                                    class="text-rose-500">*</span></label>
                                            <input id="city" name="city"
                                                   class="form-input py-2 w-full @error('city') border-red-500 @enderror"
                                                   type="text" value="{{ old('city') }}" required/>
                                            @error('city')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="sm:w-1/2">
                                            <label class="block text-sm font-medium mb-1" for="pcode">Postal Code <span
                                                    class="text-rose-500">*</span></label>
                                            <input id="pcode" name="postal_code"
                                                   class="form-input py-2 w-full @error('postal_code') border-red-500 @enderror"
                                                   type="text" value="{{ old('postal_code') }}" required/>
                                            @error('postal_code')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="country">Country <span
                                                class="text-rose-500">*</span></label>
                                        <select id="country" name="country"
                                                class="form-select py-2 w-full @error('country') border-red-500 @enderror"
                                                required>
                                            <option value="">Select country...</option>
                                            <option
                                                value="United States" {{ old('country') == 'United States' ? 'selected' : '' }}>
                                                United States
                                            </option>
                                            <option
                                                value="United Kingdom" {{ old('country') == 'United Kingdom' ? 'selected' : '' }}>
                                                United Kingdom
                                            </option>
                                            <option
                                                value="Australia" {{ old('country') == 'Australia' ? 'selected' : '' }}>
                                                Australia
                                            </option>
                                            <option
                                                value="New Zealand" {{ old('country') == 'New Zealand' ? 'selected' : '' }}>
                                                New Zealand
                                            </option>
                                            <option value="Other" {{ old('country') == 'Other' ? 'selected' : '' }}>
                                                Other...
                                            </option>
                                        </select>
                                        @error('country')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="usage-timeline">When are you
                                            willing to use the product? <span class="text-rose-500">*</span></label>
                                        <select id="usage-timeline" name="usage_timeline"
                                                class="form-select py-2 w-full @error('usage_timeline') border-red-500 @enderror"
                                                required>
                                            <option value="">Select timeline...</option>
                                            <option
                                                value="immediately" {{ old('usage_timeline') == 'immediately' ? 'selected' : '' }}>
                                                Immediately
                                            </option>
                                            <option
                                                value="within-1-month" {{ old('usage_timeline') == 'within-1-month' ? 'selected' : '' }}>
                                                Within 1 month
                                            </option>
                                            <option
                                                value="within-3-months" {{ old('usage_timeline') == 'within-3-months' ? 'selected' : '' }}>
                                                Within 3 months
                                            </option>
                                            <option
                                                value="within-6-months" {{ old('usage_timeline') == 'within-6-months' ? 'selected' : '' }}>
                                                Within 6 months
                                            </option>
                                            <option
                                                value="within-1-year" {{ old('usage_timeline') == 'within-1-year' ? 'selected' : '' }}>
                                                Within 1 year
                                            </option>
                                            <option
                                                value="just-exploring" {{ old('usage_timeline') == 'just-exploring' ? 'selected' : '' }}>
                                                Just exploring
                                            </option>
                                        </select>
                                        @error('usage_timeline')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Turnstile Widget -->
                                    <div>
                                        <x-turnstile-widget
                                            theme="light"
                                            language="en-US"
                                        />
                                        @error('cf-turnstile-response')
                                        <p class="error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <button
                                        class="btn-sm w-full text-sm text-white bg-blue-600 hover:bg-blue-700 group">
                                        Submit <span
                                            class="tracking-normal text-blue-300 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1"></span>
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
            <div class="absolute inset-0" data-aos="fade">
                <img class="opacity-10 w-full h-full object-cover" src="./images/request-demo-bg.jpg" width="760"
                     height="900" alt="Background"/>
            </div>

            <!-- Quote -->
            <div class="min-h-screen h-full flex flex-col justify-center">
                <div class="px-5 sm:px-6">
                    <div class="w-full max-w-lg mx-auto">
                        <h2 class="h3 md:text-4xl font-playfair-display text-slate-100 mb-4">AI-Powered Real Estate
                            Assistant</h2>

                    </div>
                </div>
            </div>

        </div>

    </main>
@endsection
