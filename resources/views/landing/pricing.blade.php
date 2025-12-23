@extends('landing.layouts.app')

@section('title', 'Pricing - ' . config('app.name'))

@section('content')
    <div class="flex flex-col min-h-screen overflow-hidden">

        @include('landing.partials.header')

        <!-- Page content -->
        <main class="grow mt-10">
            @include('landing.partials.pricing')

            <!-- FAQs -->
            @if (isset($landing['faqs']) && count($landing['faqs']) > 0)
                <section id="faqs">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6">
                        <div class="py-12 md:py-20 border-t border-slate-200">


                            <div class="max-w-3xl mx-auto text-center pb-12 md:pb-20">
                                <h2 class="h2 font-display text-slate-800 mb-4">
                                    Frequently Asked Questions
                                </h2>
                                <p class="text-xl text-slate-500">
                                    Everything you need to know about our AI chatbot platform.
                                </p>
                            </div>

                            <div class="max-w-4xl mx-auto">
                                <div class="space-y-4">
                                    @foreach ($landing['faqs'] as $index => $faq)
                                        <div class="bg-white rounded-lg border border-slate-200 shadow-sm"
                                            x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }" data-aos="fade-up"
                                            @if ($index > 0) data-aos-delay="{{ (int) $index * 100 }}" @endif>

                                            <button
                                                class="w-full text-left px-6 py-4 focus:outline-none rounded-lg transition-colors duration-200"
                                                :class="open ? 'bg-slate-50' : 'hover:bg-slate-50'" @click="open = !open"
                                                :aria-expanded="open">
                                                <div class="flex items-center justify-between">
                                                    <h3 class="text-lg font-semibold text-slate-800 pr-4">
                                                        {{ $faq['question'] }}
                                                    </h3>
                                                    <svg class="w-5 h-5 text-slate-500 shrink-0 transition-transform duration-200"
                                                        :class="{ 'rotate-180': open }" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </div>
                                            </button>

                                            <div x-show="open" x-transition:enter="transition ease-out duration-200"
                                                x-transition:enter-start="opacity-0 -translate-y-2"
                                                x-transition:enter-end="opacity-100 translate-y-0"
                                                x-transition:leave="transition ease-in duration-150"
                                                x-transition:leave-start="opacity-100 translate-y-0"
                                                x-transition:leave-end="opacity-0 -translate-y-2" class="px-6 pb-4">
                                                <div class="text-slate-600 leading-relaxed">
                                                    {!! nl2br(e($faq['answer'])) !!}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        </div>
                    </div>
                </section>
            @endif
        </main>
        @include('landing.partials.footer')
    </div>
@endsection
