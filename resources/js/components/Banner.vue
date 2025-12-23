<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Clock, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();

const onTrial = computed(() => page.props.onTrial);
const trialEndsAt = computed(() => page.props.trialEndsAt);
const subscriptionStartsAt = computed(() => page.props.subscriptionStartsAt);

// Default banner text for non-trial users
const defaultBannerText = 'Found a bug? Need a feature? Report it via our support system!';
</script>

<template>
    <!-- Trial Banner -->
    <div v-if="onTrial" class="fixed top-0 right-0 left-0 z-40 bg-gradient-to-r from-emerald-600 to-teal-600 text-white">
        <div class="mx-auto flex h-12 items-center justify-center px-4 md:max-w-7xl">
            <div class="flex items-center space-x-3">
                <Sparkles class="h-4 w-4" />
                <span class="text-sm font-medium"> 🎉 You're on a free trial! </span>
                <span class="text-sm opacity-90">•</span>
                <div class="flex items-center space-x-1.5">
                    <Clock class="h-3.5 w-3.5" />
                    <span class="text-sm"> Trial ends {{ trialEndsAt }} </span>
                </div>
                <span class="text-sm opacity-90">•</span>
                <span class="text-sm"> Subscription starts {{ subscriptionStartsAt }} </span>
            </div>
        </div>
    </div>

    <!-- Default Banner (for non-trial users) -->
    <div v-else class="fixed top-0 right-0 left-0 z-40 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="mx-auto flex h-12 items-center justify-center px-4 md:max-w-7xl">
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium">{{ defaultBannerText }}</span>
                <Link
                    :href="route('support')"
                    class="text-sm font-semibold underline transition-all duration-200 hover:text-blue-200 hover:no-underline"
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
