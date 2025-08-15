<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

// Banner state - easily modifiable
const bannerText = ref('Found a bug? Need a feature? Report it via our support system!');
</script>

<template>
    <div class="fixed top-0 left-0 right-0 z-40 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="mx-auto flex h-12 items-center justify-center px-4 md:max-w-7xl">
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium">{{ bannerText }}</span>
                <Link 
                    :href="route('support')" 
                    class="text-sm font-semibold underline hover:no-underline transition-all duration-200 hover:text-blue-200"
                >
                    Support →
            </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* No custom styles needed */
</style>
