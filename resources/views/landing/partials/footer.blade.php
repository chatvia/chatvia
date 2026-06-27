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
                        <img src="{{ asset('images/logo.svg') }}" class="w-12" alt="{{ config('app.name') }} Logo" />
                    </a>
                </div>

                <address class="not-italic text-sm text-slate-500 leading-relaxed">
                    Akkadian<br>
                    Skovbrynet 50, 1 tv<br>
                    4700 Næstved, Denmark<br>
                    VAT DK35709738
                </address>

            </div>

            <!-- Legal links -->
            <div class="flex flex-wrap gap-6 md:gap-8">
                <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                    href="{{ route('terms') }}">{{ __('landing.footer.terms') }}</a>
                <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                    href="{{ route('privacy') }}">{{ __('landing.footer.privacy') }}</a>
                <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                    href="{{ route('refund') }}">{{ __('landing.footer.refund') }}</a>
                <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                    href="{{ route('subprocessors') }}">{{ __('landing.footer.subprocessors') }}</a>
                <a class="text-sm font-medium text-slate-500 hover:text-blue-600 transition duration-150 ease-in-out"
                    href="{{ route('contact') }}">{{ __('landing.footer.contact') }}</a>
            </div>

        </div>

        <!-- Bottom area -->
        <div class="md:flex md:items-center md:justify-between py-6 md:py-8 border-t border-slate-200">

            <ul class="flex space-x-6 rtl:space-x-reverse mb-4 md:order-1 md:ms-4 md:mb-0">
                <li>
                    <a class="text-blue-500 hover:text-blue-600 transition duration-150 ease-in-out"
                        href="https://facebook.com/chatvia.ai" aria-label="Facebook">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M20 10.025C20 4.491 15.52 0 10 0S0 4.491 0 10.025c0 4.852 3.44 8.892 8 9.825v-6.817H6v-3.008h2V7.52a3.508 3.508 0 0 1 3.5-3.509H14v3.008h-2c-.55 0-1 .45-1 1.002v2.005h3v3.008h-3V20c5.05-.501 9-4.772 9-9.975Z" />
                        </svg>
                    </a>
                </li>
                <li>
                    <a class="text-blue-500 hover:text-blue-600 transition duration-150 ease-in-out"
                        href="https://m.me/chatvia.ai" aria-label="Messenger">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M12 0C5.373 0 0 4.974 0 11.111c0 3.498 1.744 6.614 4.469 8.654V24l4.088-2.242c1.092.301 2.246.464 3.443.464 6.627 0 12-4.975 12-11.111C24 4.974 18.627 0 12 0zm1.191 14.963l-3.055-3.26-5.963 3.26L10.732 8l3.131 3.259L19.752 8l-6.561 6.963z" />
                        </svg>
                    </a>
                </li>
                <li>
                    <a class="text-blue-500 hover:text-blue-600 transition duration-150 ease-in-out"
                        href="https://t.me/chatvia.ai" aria-label="Telegram">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                        </svg>
                    </a>
                </li>
            </ul>

            <!-- Copyrights note -->
            <div class="text-sm text-slate-500 me-4">&copy; {{ config('app.name') }}.
                {{ __('landing.footer.made_with_love') }}
            </div>

        </div>

    </div>
</footer>
