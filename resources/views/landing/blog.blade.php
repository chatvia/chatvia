@extends('landing.layouts.app')

@section('title', 'Blog')

@section('content')
    @include('landing.partials.header')

    <main class="grow">

    <!-- Hero -->
    <section class="relative">

        <!-- Dark background -->
        <div class="absolute inset-0 bg-slate-900 pointer-events-none -z-10" aria-hidden="true"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-6">
            <div class="pt-32 pb-12 md:pt-40 md:pb-20">

                <!-- Section header -->
                <div class="max-w-3xl mx-auto text-center">
                    <h1 class="h1 font-playfair-display text-slate-100 mb-4">
                        Blog
                    </h1>
                    <p class="text-xl text-slate-400 mb-8">
                        Lessons designed to help you kick start and grow your business and turn your idea into a thriving empire.
                    </p>
                    <!-- Subscribe form -->
                    <form>
                        <div class="flex flex-col sm:flex-row justify-center max-w-xs mx-auto sm:max-w-md">
                            <input type="email" class="form-input w-full bg-slate-800 border border-slate-700 focus:border-slate-600 text-white placeholder-slate-500 mb-2 sm:mb-0 sm:mr-2" placeholder="Your email" aria-label="Your email…" />
                            <button class="btn text-white bg-blue-600 hover:bg-blue-700">Subscribe</button>
                        </div>
                        <!-- Success message -->
                        <!-- <p class="text-xs text-slate-500 mt-3 italic">Thanks for subscribing!</p> -->
                    </form>
                </div>

            </div>
        </div>
    </section>

    <!-- Sticky article -->
    <section>

        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="py-8 md:py-16">

                <article class="max-w-sm mx-auto space-y-5 md:max-w-none md:flex md:items-center md:space-y-0 md:space-x-8 lg:space-x-16">
                    <!-- Image -->
                    <a class="relative block group overflow-hidden md:w-1/2" href="{{ route('blog.post', ['post' => $featured->slug]) }}" data-aos="fade-down">
                        <img class="w-full aspect-16/9 md:aspect-27/17 object-cover group-hover:scale-105 transition duration-700 ease-out" src="{{ $featured->img_url }}" width="540" height="340" alt="News 01" />
                        <div class="absolute top-6 right-6">
                            <svg class="w-8 h-8" viewBox="0 0 32 32">
                                <circle class="fill-slate-900" fill-opacity=".48"cx="16" cy="16" r="16" />
                                <path class="fill-yellow-500" d="M21.954 14.29A.5.5 0 0 0 21.5 14h-5.36l.845-3.38a.5.5 0 0 0-.864-.446l-6 7A.5.5 0 0 0 10.5 18h5.359l-.844 3.38a.5.5 0 0 0 .864.445l6-7a.5.5 0 0 0 .075-.534Z" />
                            </svg>
                        </div>
                    </a>
                    <!-- Content -->
                    <div class="md:w-1/2" data-aos="fade-up">
                        <header>
                            <h2 class="h4 md:text-4xl lg:text-5xl font-playfair-display mb-3">
                                <a class="text-slate-800 hover:underline hover:decoration-blue-100" href="{{ route('blog.post', ['post' => $featured->slug]) }}">
                                    {{ $featured->title }}
                                </a>
                            </h2>
                        </header>
                        <p class="text-lg text-slate-500 grow">
                            {{ $featured->excerpt  }}
                        </p>
                        <footer class="flex items-center mt-4">
                            <div>
                                <span class="font-medium transition duration-150 ease-in-out">Ahmad Mayahi</span>
                                <span class="text-slate-300"> · </span>
                                <span class="text-slate-500">{{ $featured->published_at->diffForHumans() }}</span>
                            </div>
                        </footer>
                    </div>
                </article>

            </div>

        </div>
    </section>

    <!-- All articles -->
    <section class="bg-slate-50">

        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="py-8 md:py-16 space-y-16">
                <div>
                    <div class="max-w-sm mx-auto md:max-w-none grid gap-12 md:grid-cols-3 md:gap-x-6 md:gap-y-8 items-start">
                        @foreach($posts as $post)
                            <article class="h-full flex flex-col space-y-5" data-aos="fade-up">
                                <!-- Image -->
                                <a class="block group overflow-hidden" href="{{ route('blog.post', ['post' => $post->slug]) }}">
                                    <img class="w-full aspect-16/9 md:aspect-27/17 object-cover group-hover:scale-105 transition duration-700 ease-out" src="./images/blog-post-02.jpg" width="540" height="340" alt="News 02" />
                                </a>
                                <!-- Content -->
                                <div class="grow flex flex-col">
                                    <header>
                                        <h3 class="h4 font-playfair-display mb-3">
                                            <a class="text-slate-800 hover:underline hover:decoration-blue-100" href="blog-post.html">A sneak peek of the new Tidy Web Portal</a>
                                        </h3>
                                    </header>
                                    <p class="text-lg text-slate-500 grow">Lorem ipsum dolor sit amet, consectetur elit, sed do eiusmod tempor incididunt.</p>
                                    <footer class="flex items-center mt-4">
                                        <a href="#0">
                                            <img class="rounded-full shrink-0 mr-3" src="./images/news-author-02.jpg" width="32" height="32" alt="Author 02" />
                                        </a>
                                        <div>
                                            <a class="font-medium text-slate-800 hover:text-blue-600 transition duration-150 ease-in-out" href="#0">Knut Mayer</a>
                                            <span class="text-slate-300"> · </span>
                                            <span class="text-slate-500">Sep 24, 2021</span>
                                        </div>
                                    </footer>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <!-- CTA box -->
                @include('landing.partials.blog.cta')


            </div>

        </div>
    </section>

</main>

    @include('landing.partials.footer')
@endsection


