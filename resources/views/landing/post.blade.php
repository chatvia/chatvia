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
                                <h1 class="h2 font-playfair-display text-slate-100 mb-6">Introducing the Market Builder Tool</h1>
                            </div>
                            <!-- Article meta -->
                            <div class="md:flex md:items-center md:justify-between mt-3" data-aos="fade-up">
                                <!-- Author meta -->
                                <div class="flex items-center justify-center">
                                    <a href="#0">
                                        <img class="rounded-full shrink-0 mr-3" src="./images/news-author-01.jpg" width="32" height="32" alt="Author 01" />
                                    </a>
                                    <div>
                                        <a class="font-medium text-slate-200 hover:text-slate-100 transition duration-150 ease-in-out" href="#0">Patricia Williams</a>
                                        <span class="text-slate-600"> · </span>
                                        <span class="text-slate-400">Sep 24, 2021</span>
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
                            <img class="w-full" src="./images/news-single.jpg" width="768" height="432" alt="News single" />
                        </figure>

                    </div>
                </div>
            </div>
        </header>

        <!-- Article content -->
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6">
            <div class="max-w-3xl mx-auto">

                <div class="text-lg text-slate-500">
                    <p class="mb-8">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Quis enim lobortis scelerisque fermentum. Neque sodales ut etiam sit amet <strong class="font-medium text-slate-800">how to manage anything malesuada</strong> proin libero nunc consequat interdum varius. Quam pellentesque nec nam aliquam sem <a class="font-medium text-blue-600 hover:underline" href="#0">et tortor consequat</a>. Pellentesque adipiscing commodo elit at imperdiet. Semper auctor neque vitae tempus quam pellentesque nec. Amet dictum sit amet justo donec enim diam. <strong class="font-medium text-slate-800">Varius sit amet mattis vulputate enim</strong> nulla aliquet porttitor. Odio pellentesque diam volutpat commodo sed. Elit sed vulputate mi sit amet mauris commodo quis imperdiet.
                    </p>
                    <p class="mb-8">
                        Pretium lectus quam id leo. Urna et pharetra pharetra massa massa. Eget sit amet tellus cras adipiscing enim eu. Neque aliquam vestibulum morbi blandit cursus risus.
                    </p>
                    <div class="flex justify-center mb-8" aria-hidden="true">
                        <svg class="fill-slate-400" height="4" width="36">
                            <circle cx="18" cy="2" r="2" />
                            <circle cx="2" cy="2" r="2" />
                            <circle cx="34" cy="2" r="2" />
                        </svg>
                    </div>
                    <h2 class="h3 font-playfair-display text-slate-800 mb-4">1 / Adaptation of the mobile version</h2>
                    <p class="mb-8">
                        Aenean sed adipiscing diam donec adipiscing tristique risus nec feugiat auctor urna nunc id cursus metus aliquam eleifend, arcu dictum varius duis at consectetur lorem donec massa sapien, sed risus ultricies tristique nulla aliquet.
                    </p>
                    <figure class="mb-8 lg:-ml-32 lg:-mr-32">
                        <img class="w-full" src="./images/news-inner-image.jpg" width="1024" height="576" alt="News inner" />
                    </figure>
                    <h3 class="h4 font-playfair-display text-slate-800 mb-4">Why this change seems mostly visual</h3>
                    <p class="mb-8">
                        Et leo duis ut diam quam nulla porttitor porttitor lacus luctus accumsan tortor, lorem dolor sed viverra ipsum nunc aliquet bibendum enim eu augue ut lectus arcu bibendum at. Non sodales neque sodales ut etiam sit. Venenatis urna cursus eget nunc scelerisque viverra mauris in aliquam.
                    </p>
                    <div class="flex justify-center mb-8" aria-hidden="true">
                        <svg class="fill-slate-400" height="4" width="36">
                            <circle cx="18" cy="2" r="2" />
                            <circle cx="2" cy="2" r="2" />
                            <circle cx="34" cy="2" r="2" />
                        </svg>
                    </div>
                    <h2 class="h3 font-playfair-display text-slate-800 mb-4">2 / Streamline the experience with custom flows</h2>
                    <p class="mb-8">
                        Aenean sed adipiscing diam donec adipiscing tristique risus nec feugiat auctor urna nunc id cursus metus aliquam eleifend, arcu dictum varius duis at consectetur lorem donec massa sapien, sed risus ultricies tristique nulla aliquet. Morbi tristique senectus et netus et, nibh nisl condimentum id venenatis a condimentum vitae sapien.
                    </p>
                    <p class="mb-8">
                        Aenean sed adipiscing diam donec adipiscing tristique risus nec feugiat auctor urna nunc id cursus metus aliquam eleifend:
                    </p>
                    <p class="mb-8 border-l-2 border-slate-800 pl-3 lg:-ml-3.5">
                        <strong class="font-medium text-slate-800">Example:</strong> Adipiscing diams - 2px, feugiat auctor - 3px;
                    </p>
                    <div class="flex justify-center mb-8" aria-hidden="true">
                        <svg class="fill-slate-400" height="4" width="36">
                            <circle cx="18" cy="2" r="2" />
                            <circle cx="2" cy="2" r="2" />
                            <circle cx="34" cy="2" r="2" />
                        </svg>
                    </div>
                    <h2 class="h3 font-playfair-display text-slate-800 mb-4">3 / Simplified navigation flow</h2>
                    <p class="mb-8">
                        Arcu dictum varius duis at consectetur lorem donec massa sapien, sed risus ultricies tristique nulla aliquet.
                    </p>
                    <figure class="mb-8">
                        <img class="w-full" src="./images/news-inner-image-02.jpg" width="768" height="432" alt="News inner 02" />
                    </figure>
                    <p class="mb-8 border-l-2 border-slate-800 pl-3 lg:-ml-3.5">
                        <strong class="font-medium text-slate-800"><em class="italic">Morbi tristique senectus et netus et, nibh nisl condimentum id venenatis a condimentum vitae sapien risus nec feugiat auctor urna nunc.</em></strong>
                    </p>
                    <div class="flex justify-center mb-8" aria-hidden="true">
                        <svg class="fill-slate-400" height="4" width="36">
                            <circle cx="18" cy="2" r="2" />
                            <circle cx="2" cy="2" r="2" />
                            <circle cx="34" cy="2" r="2" />
                        </svg>
                    </div>
                    <h2 class="h3 font-playfair-display text-slate-800 mb-4">Handy tools</h2>
                    <p class="mb-8">
                        Arcu dictum varius duis at consectetur lorem donec massa sapien, sed risus ultricies tristique nulla aliquet:
                    </p>
                    <ul class="list-disc list-inside mb-8">
                        <li>
                            <a class="font-medium text-slate-800 underline hover:no-underline" href="#0">Designing Better Symbols</a>
                        </li>
                        <li>
                            <a class="font-medium text-slate-800 underline hover:no-underline" href="#0">Upgrading Smart Objects</a>
                        </li>
                        <li>
                            <a class="font-medium text-slate-800 underline hover:no-underline" href="#0">Designing Vs Coding</a>
                        </li>
                        <li>
                            <a class="font-medium text-slate-800 underline hover:no-underline" href="#0">Understanding Smart Layouts</a>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </article>

    <!-- Divider -->
    <div class="flex justify-center mb-8" aria-hidden="true">
        <svg class="fill-slate-400" height="4" width="36">
            <circle cx="18" cy="2" r="2" />
            <circle cx="2" cy="2" r="2" />
            <circle cx="34" cy="2" r="2" />
        </svg>
    </div>

    <!-- Newsletter -->
    <section>
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="pb-12 md:pb-20 border-b border-slate-200">

                <!-- CTA box -->
                <div class="max-w-3xl mx-auto bg-slate-900 rounded-xs p-8">

                    <div class="flex flex-col justify-between items-center">

                        <!-- CTA content -->
                        <div class="text-center">
                            <div class="inline-flex mb-3">
                                <svg class="w-12 h-12" viewBox="0 0 48 48">
                                    <circle class="fill-blue-600" cx="24" cy="24" r="24" />
                                    <path class="fill-blue-100" d="m12.397 22.797 2.577 1.952 9-4-6.977 5.499v6.749a1 1 0 0 0 1.765.643l3.13-3.72 6.505 4.877a1 1 0 0 0 1.58-.6l4-20a1 1 0 0 0-1.351-1.125l-20 8a1 1 0 0 0-.23 1.725Z" fill="#DBEAFE" />
                                    <path class="fill-blue-600" d="m33.917 14.499-3.94 19.698a1 1 0 0 1-1.58.6l-6.505-4.876 12.025-15.422Z" fill-opacity=".5" fill="#2174EA" />
                                </svg>
                            </div>
                            <h3 class="h4 font-playfair-display text-slate-100 mb-2">Sign up for Tidy Digest</h3>
                            <p class="text-slate-400 mb-6">Stay in the loop with the Tidy industry - get the latest news, stories and tools.</p>

                            <!-- CTA form -->
                            <form class="w-full">
                                <div class="flex flex-col sm:flex-row justify-center max-w-xs mx-auto sm:max-w-md">
                                    <input type="email" class="form-input w-full appearance-none bg-slate-800 border border-slate-700 focus:border-slate-600 rounded-xs px-4 py-3 mb-2 sm:mb-0 sm:mr-2 text-white placeholder-slate-500" placeholder="Your email" aria-label="Your email…" />
                                    <button class="btn text-white bg-blue-600 hover:bg-blue-700">Subscribe</button>
                                </div>
                                <!-- Success message -->
                                <!-- <p class="text-xs text-slate-500 mt-3 italic">Thanks for subscribing!</p> -->
                                <p class="text-xs text-slate-500 mt-3 italic">No spam. You can unsubscribe at any time.</p>
                            </form>
                        </div>

                    </div>

                </div>

            </div>
        </div>
    </section>

</main>
    @include('landing.partials.footer')
@endsection
