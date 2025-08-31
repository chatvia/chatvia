<!-- Image Carousel Section - Full Width -->
<div class="px-4 sm:px-6">
    <div class="relative max-w-5xl mx-auto" x-data="{
        currentImage: 0,
        images: [
            '{{ asset('images/landing/how-to/001.png') }}',
        ]
    }" x-init="setInterval(() => { currentImage = (currentImage + 1) % images.length }, 2000)">

        <!-- Main Image Container -->
        <div class="relative overflow-hidden rounded-lg shadow-2xl">
            <!-- Current Image -->
            <img
                :src="images[currentImage]"
                :alt="`ChatVIA AI Agents Platform Demo - Step ${currentImage + 1}`"
                class="w-full h-auto transition-opacity duration-500"
                loading="lazy"
                width="800"
                height="450"
            >

                    <!-- Image Counter Overlay -->
        <div class="absolute bottom-4 right-4 bg-black bg-opacity-50 text-white px-3 py-1 rounded-full text-sm font-medium" x-text="`${currentImage + 1} / 5`">
        </div>
        </div>




    </div>
</div>
