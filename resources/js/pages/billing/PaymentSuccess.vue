<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { CheckCircle, Loader2 } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

const countdown = ref(5);
let intervalId: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    intervalId = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            if (intervalId) clearInterval(intervalId);
            router.visit(route('billing.index'));
        }
    }, 1000);
});

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
});

const redirectNow = () => {
    if (intervalId) clearInterval(intervalId);
    router.visit(route('billing.index'));
};
</script>

<template>
    <AppLayout title="Payment Successful">
        <div class="flex min-h-[80vh] items-center justify-center px-4">
            <div class="max-w-md text-center">
                <!-- Success Icon with Animation -->
                <div class="relative mb-8">
                    <div class="mx-auto flex h-24 w-24 animate-[scale_0.5s_ease-out] items-center justify-center rounded-full bg-emerald-100">
                        <CheckCircle class="h-14 w-14 text-emerald-600" stroke-width="1.5" />
                    </div>
                    <!-- Subtle pulse ring -->
                    <div class="absolute inset-0 mx-auto h-24 w-24 animate-ping rounded-full bg-emerald-400/20" style="animation-duration: 2s"></div>
                </div>

                <!-- Text Content -->
                <h1 class="mb-3 text-3xl font-semibold text-slate-900">Payment Received</h1>
                <p class="mb-8 text-slate-600">Thank you! Your payment has been processed successfully. We're setting up your subscription now.</p>

                <!-- Countdown -->
                <div class="mb-6 rounded-xl bg-slate-50 p-6">
                    <div class="flex items-center justify-center gap-3 text-slate-600">
                        <Loader2 class="h-5 w-5 animate-spin text-slate-400" />
                        <span
                            >Redirecting in <span class="font-semibold text-slate-900 tabular-nums">{{ countdown }}</span> seconds...</span
                        >
                    </div>
                </div>

                <!-- Manual redirect button -->
                <button @click="redirectNow" class="text-sm text-slate-500 underline underline-offset-2 transition-colors hover:text-slate-700">
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
