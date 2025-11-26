<!-- Video Modal -->
<div id="video-modal" class="fixed inset-0 z-50" x-data="{ open: false }" x-show="open" x-cloak
     @open-video-modal.window="open = true; $nextTick(() => { $refs.videoFrame.src = $event.detail.src })"
     @keydown.escape.window="open = false; $refs.videoFrame.src = ''">

    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/80 transition-opacity" x-show="open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false; $refs.videoFrame.src = ''"></div>

    <!-- Modal Content -->
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-4xl" x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">

            <!-- Close Button -->
            <button @click="open = false; $refs.videoFrame.src = ''"
                    class="absolute -top-10 right-0 text-white hover:text-slate-300 transition-colors cursor-pointer">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <!-- Video Container -->
            <div class="relative bg-black rounded-lg overflow-hidden" style="padding-top: 57.69%;">
                <iframe x-ref="videoFrame"
                        src=""
                        class="absolute inset-0 w-full h-full"
                        frameborder="0"
                        allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen></iframe>
            </div>
        </div>
    </div>
</div>

