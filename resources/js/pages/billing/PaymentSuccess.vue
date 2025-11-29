<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { CheckCircle, Loader2 } from 'lucide-vue-next'

const countdown = ref(5)
let intervalId: ReturnType<typeof setInterval> | null = null

onMounted(() => {
    intervalId = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) {
            if (intervalId) clearInterval(intervalId)
            router.visit(route('billing.index'))
        }
    }, 1000)
})

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId)
})

const redirectNow = () => {
    if (intervalId) clearInterval(intervalId)
    router.visit(route('billing.index'))
}
</script>

<template>
    <AppLayout title="Payment Successful">
        <div class="min-h-[80vh] flex items-center justify-center px-4">
            <div class="text-center max-w-md">
                <!-- Success Icon with Animation -->
                <div class="relative mb-8">
                    <div class="w-24 h-24 mx-auto bg-emerald-100 rounded-full flex items-center justify-center animate-[scale_0.5s_ease-out]">
                        <CheckCircle class="w-14 h-14 text-emerald-600" stroke-width="1.5" />
                    </div>
                    <!-- Subtle pulse ring -->
                    <div class="absolute inset-0 w-24 h-24 mx-auto bg-emerald-400/20 rounded-full animate-ping" style="animation-duration: 2s;"></div>
                </div>

                <!-- Text Content -->
                <h1 class="text-3xl font-semibold text-slate-900 mb-3">
                    Payment Received
                </h1>
                <p class="text-slate-600 mb-8">
                    Thank you! Your payment has been processed successfully. We're setting up your subscription now.
                </p>

                <!-- Countdown -->
                <div class="bg-slate-50 rounded-xl p-6 mb-6">
                    <div class="flex items-center justify-center gap-3 text-slate-600">
                        <Loader2 class="w-5 h-5 animate-spin text-slate-400" />
                        <span>Redirecting in <span class="font-semibold text-slate-900 tabular-nums">{{ countdown }}</span> seconds...</span>
                    </div>
                </div>

                <!-- Manual redirect button -->
                <button
                    @click="redirectNow"
                    class="text-sm text-slate-500 hover:text-slate-700 underline underline-offset-2 transition-colors"
                >
                    Go to billing now
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes scale {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}
</style>

