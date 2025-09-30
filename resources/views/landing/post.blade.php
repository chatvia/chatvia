@extends('landing.layouts.app')

@section('title', 'Post')

@section('content')
    @include('landing.partials.header')
    <main class="grow">

    <!-- Blog post -->
    <article>

        <header class="relative">

            <!-- Dark background -->
            <div class="absolute inset-0 bg-slate-900 pointer-events-none -z-10 mb-36 lg:mb-0 lg:h-[48rem] [clip-path:polygon(0_0,_5760px_0,_5760px_calc(100%_-_352px),_0_100%)]" aria-hidden="true"></div>

            <div class="relative max-w-6xl mx-auto px-4 sm:px-6">
                <div class="pt-32 md:pt-40 pb-8">
                    <div class="max-w-3xl mx-auto">

                        <div class="mb-8">
                            <!-- Title and excerpt -->
                            <div class="text-center md:text-left">
                                <a class="inline-flex font-semibold text-blue-600 hover:text-blue-500 transition duration-150 ease-in-out group mb-2" href="blog.html" data-aos="fade-down"><span class="tracking-normal text-blue-600 group-hover:-translate-x-0.5 transition-transform duration-150 ease-in-out mr-1">&lt;-</span> Back to Blog</a>
                                <h1 class="h2 font-playfair-display text-slate-100 mb-6">
                                    {{ $post->title }}
                                </h1>
                            </div>
                            <!-- Article meta -->
                            <div class="md:flex md:items-center md:justify-between mt-3" data-aos="fade-up">
                                <!-- Author meta -->
                                <div class="flex items-center justify-center">
                                    <a href="#0">
                                        <img class="rounded-full shrink-0 mr-3" src="./images/news-author-01.jpg" width="32" height="32" alt="Author 01" />
                                    </a>
                                    <div>
                                        <a class="font-medium text-slate-200 hover:text-slate-100 transition duration-150 ease-in-out" href="#0">Ahmad Mayahi</a>
                                        <span class="text-slate-600"> · </span>
                                        <span class="text-slate-400">{{ $post->published_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                                <!-- Social links -->
                                <div class="flex justify-center mt-4 md:mt-0">
                                    <ul class="flex space-x-5 mb-4 md:order-1 md:ml-4 md:mb-0">
                                        <li>
                                            <a class="text-blue-600 hover:text-blue-500 transition duration-150 ease-in-out" href="#0" aria-label="Facebook">
                                                <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M20 10.025C20 4.491 15.52 0 10 0S0 4.491 0 10.025c0 4.852 3.44 8.892 8 9.825v-6.817H6v-3.008h2V7.52a3.508 3.508 0 0 1 3.5-3.509H14v3.008h-2c-.55 0-1 .45-1 1.002v2.005h3v3.008h-3V20c5.05-.501 9-4.772 9-9.975Z" />
                                                </svg>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="text-blue-600 hover:text-blue-500 transition duration-150 ease-in-out" href="#0" aria-label="Twitter">
                                                <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="m6.329 1 4.369 5.594L15.75 1h3.068L12.12 8.414 20 18.5h-6.172l-4.833-6.116L3.464 18.5H.395l7.169-7.928L0 1h6.329Zm-.925 1.684H3.583l11.093 14.04h1.7L5.404 2.684Z" />
                                                </svg>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="text-blue-600 hover:text-blue-500 transition duration-150 ease-in-out" href="#0" aria-label="Pinterest">
                                                <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M10 0C4.5 0 0 4.5 0 10c0 4.25 2.667 7.833 6.333 9.333-.083-.75-.166-2 0-2.833.167-.75 1.167-5 1.167-5s-.25-.667-.25-1.5c0-1.417.833-2.417 1.833-2.417.834 0 1.25.667 1.25 1.417 0 .833-.583 2.167-.833 3.333-.25 1 .5 1.834 1.5 1.834 1.75 0 3.167-1.834 3.167-4.584 0-2.416-1.75-4.083-4.167-4.083-2.833 0-4.5 2.167-4.5 4.333 0 .834.333 1.75.75 2.25.083.084.083.167.083.25-.083.334-.25 1-.25 1.167-.083.167-.166.25-.333.167-1.25-.584-2-2.417-2-3.834 0-3.166 2.333-6.083 6.583-6.083 3.5 0 6.167 2.5 6.167 5.75 0 3.417-2.167 6.25-5.167 6.25-1 0-2-.5-2.333-1.167 0 0-.5 1.917-.583 2.417-.25.833-.834 1.917-1.25 2.583C8 19.833 9 20 10 20c5.5 0 10-4.5 10-10S15.5 0 10 0Z" />
                                                </svg>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Article image -->
                        <figure>
                            <img class="w-full" src="{{ $post->img_url }}" width="768" height="432" alt="News single" />
                        </figure>

                    </div>
                </div>
            </div>
        </header>

        <!-- Article content -->
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6">
            <div class="max-w-3xl mx-auto">
                <article class="prose prose-stone max-w-none">
                    {!! $post->html !!}
                </article>
            </div>
        </div>
    </article>

    <!-- Divider -->
    <div class="flex justify-center mb-8 mt-8" aria-hidden="true">
        <svg class="fill-slate-400" height="4" width="36">
            <circle cx="18" cy="2" r="2" />
            <circle cx="2" cy="2" r="2" />
            <circle cx="34" cy="2" r="2" />
        </svg>
    </div>

    <!-- Newsletter -->
    @include('landing.partials.newsletter')
</main>
    @include('landing.partials.footer')
@endsection
