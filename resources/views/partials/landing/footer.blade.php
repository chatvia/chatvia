<!-- Site footer -->
<footer class="bg-zinc-50 border-t border-zinc-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="py-8 md:py-12">
            <!-- Main footer content -->
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <!-- Logo and copyright -->
                <div class="flex flex-col items-center md:items-start space-y-2">
                    <a class="flex items-center justify-center w-12 h-12"
                       href="{{ route('home') }}">
                        <img src="{{ asset('images/logo.svg') }}"  alt="ChatVIA">
                    </a>
                    <div class="text-sm text-zinc-500">&copy; 2025-present ChatVIA. Open source under the MIT License.</div>
                    @include('partials.company.address', ['class' => 'not-italic text-sm text-zinc-500 leading-relaxed text-center md:text-start'])
                    <div class="flex items-center gap-2 text-xs text-blue-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span>🇪🇺 EU Hosted • GDPR Compliant • Open Source AI</span>
                    </div>
                </div>

                <!-- Essential links -->
                <div class="flex flex-wrap justify-center gap-6 text-sm">
                    <a class="text-zinc-500 hover:text-zinc-900 transition" href="{{ route('privacy') }}">Privacy Policy</a>
                    <a class="text-zinc-500 hover:text-zinc-900 transition" href="{{ route('terms') }}">Terms of Service</a>
                    <a class="text-zinc-500 hover:text-zinc-900 transition" href="{{ route('refund') }}">Refund Policy</a>
                    <a class="text-zinc-500 hover:text-zinc-900 transition" href="{{ route('contact') }}">Contact</a>
                </div>
            </div>
        </div>
    </div>
</footer>
