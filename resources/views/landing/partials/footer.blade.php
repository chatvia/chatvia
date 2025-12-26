<!-- Site footer -->
<footer>
    <div class="max-w-6xl mx-auto px-4 sm:px-6">

        <!-- Top area: Blocks -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between py-8 md:py-12">

            <!-- Logo and tagline -->
            <div class="mb-6 md:mb-0">
                <div class="mb-2">
                    <!-- Logo -->
                    <a class="inline-flex text-blue-600 transition duration-150 ease-in-out" href="{{ route('home') }}"
                        aria-label="{{ config('app.name') }}">
                        <img src="{{ asset('images/logo.png') }}" class="w-12" alt="{{ config('app.name') }} Logo" />
                    </a>
                </div>

            </div>

            <!-- Legal links -->
            <div class="flex flex-wrap gap-6 md:gap-8">
                <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                    href="{{ route('terms') }}">Terms of Service</a>
                <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                    href="{{ route('privacy') }}">Privacy</a>
                <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                    href="{{ route('refund') }}">Refund Policy</a>
                <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                    href="{{ route('changelog') }}">Changelog</a>
            </div>

        </div>

        <!-- Bottom area -->
        <div class="md:flex md:items-center md:justify-between py-6 md:py-8 border-t border-slate-200">

            <ul class="flex space-x-6 mb-4 md:order-1 md:ml-4 md:mb-0">
                <li>
                    <a class="text-blue-500 hover:text-blue-600 transition duration-150 ease-in-out"
                        href="https://facebook.com/chatviaai" aria-label="Facebook">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M20 10.025C20 4.491 15.52 0 10 0S0 4.491 0 10.025c0 4.852 3.44 8.892 8 9.825v-6.817H6v-3.008h2V7.52a3.508 3.508 0 0 1 3.5-3.509H14v3.008h-2c-.55 0-1 .45-1 1.002v2.005h3v3.008h-3V20c5.05-.501 9-4.772 9-9.975Z" />
                        </svg>
                    </a>
                </li>
                <li>
                    <a class="text-blue-500 hover:text-blue-600 transition duration-150 ease-in-out"
                        href="https://www.linkedin.com/company/chatvia-ai" aria-label="LinkedIn">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M18.6 0H1.4C.6 0 0 .6 0 1.4v17.1C0 19.4.6 20 1.4 20h17.1c.8 0 1.4-.6 1.4-1.4V1.4C20 .6 19.4 0 18.6 0zM6 17H3V8h3v9zM4.5 6.3c-1 0-1.8-.8-1.8-1.8s.8-1.8 1.8-1.8 1.8.8 1.8 1.8-.8 1.8-1.8 1.8zM17 17h-3v-4.4c0-1.1 0-2.5-1.5-2.5s-1.7 1.2-1.7 2.4V17H8V8h2.8v1.2h.1c.4-.7 1.3-1.5 2.7-1.5 2.9 0 3.4 1.9 3.4 4.4V17z" />
                        </svg>
                    </a>
                </li>
            </ul>

            <!-- Copyrights note -->
            <div class="text-sm text-slate-500 mr-4">&copy; {{ config('app.name') }}.
                Made with ❤️ in Denmark
            </div>

        </div>

    </div>
</footer>
