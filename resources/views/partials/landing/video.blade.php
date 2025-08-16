<!-- Video Section - Full Width -->
<div class="px-4 sm:px-6">
    <div class="relative max-w-5xl mx-auto" x-data="{ showModal: false }">
        <!-- Video Thumbnail Container -->
        <div class="relative cursor-pointer group" @click="showModal = true">

            <!-- Main Image -->
            <img
                    src="{{ asset('images/landing/intro.png') }}"
                    alt="ChatVIA AI Agents Platform Demo - Create custom AI chatbots with your data"
                    class="w-full rounded-lg shadow-2xl"
                    loading="lazy"
                    width="800"
                    height="450"
            >

            <!-- Play Button Overlay - Centered on top -->
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="w-24 h-24 bg-white bg-opacity-90 backdrop-blur-sm rounded-full shadow-xl flex items-center justify-center group-hover:scale-110 group-hover:bg-opacity-100 transition-all duration-300">
                    <svg class="w-10 h-10 text-zinc-900 ml-1" fill="currentColor"
                         viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </div>
            </div>

            <!-- Hover Overlay -->
            <div class="absolute inset-0 rounded-lg transition-all duration-300"></div>
        </div>

        <!-- Video Modal -->
        <div
                x-show="showModal"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75"
                @click.self="showModal = false"
                @keydown.escape.window="showModal = false"
                style="display: none;"
        >
            <div class="relative max-w-4xl w-full bg-white rounded-lg shadow-2xl overflow-hidden"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">

                <!-- Close Button -->
                <button
                        @click="showModal = false"
                        class="absolute top-4 right-4 z-10 w-8 h-8 bg-black bg-opacity-50 hover:bg-opacity-75 text-white rounded-full flex items-center justify-center transition-all duration-200"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <!-- Video Player -->
                <video
                        class="w-full aspect-video"
                        controls
                        autoplay
                        preload="metadata"
                        x-init="$watch('showModal', value => { if (!value) $el.pause(); $el.currentTime = 0; })"
                >
                    <source src="https://fsn1.your-objectstorage.com/chatvia/landing/rDiqJr8xwTVe8WLSimGKvRXlykKl9BvNIXOnFJI9.mp4" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>
    </div>
</div>
