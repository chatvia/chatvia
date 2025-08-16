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
                    <div class="text-sm text-zinc-500">&copy; 2025 ChatVIA. All rights reserved.</div>
                </div>

                <!-- Essential links -->
                <div class="flex flex-wrap justify-center gap-6 text-sm">
                    <a class="text-zinc-500 hover:text-zinc-900 transition" href="{{ route('privacy') }}">Privacy Policy</a>
                    <a class="text-zinc-500 hover:text-zinc-900 transition" href="{{ route('terms') }}">Terms of Service</a>
                    <a class="text-zinc-500 hover:text-zinc-900 transition" href="{{ route('refund') }}">Refund Policy</a>
                </div>
            </div>
        </div>
    </div>
</footer>
