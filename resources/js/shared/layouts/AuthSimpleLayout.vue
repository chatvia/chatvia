<script setup lang="ts">
import AppLogoIcon from '@/shared/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/shared/components/LanguageSwitcher.vue';
import { Toaster } from '@/shared/ui/sonner';
import { home } from '@/routes';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const logoHref = home.url();

// The install's own icon (Settings → Platform), or null for the ChatVia.ai mark.
const page = usePage();
const customLogo = computed(() => page.props.logo ?? null);
</script>

<template>
    <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div class="absolute start-4 top-4">
            <LanguageSwitcher />
        </div>
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <a :href="logoHref" class="flex flex-col items-center gap-2 font-medium">
                        <div class="mb-1 flex items-center justify-center rounded-md">
                            <img v-if="customLogo" :src="customLogo" alt="" class="size-10 object-contain" />
                            <AppLogoIcon v-else class="size-10" />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </a>
                    <div class="space-y-2 text-center">
                        <h1 class="text-2xl font-medium">{{ title }}</h1>
                        <p class="text-muted-foreground text-center text-sm">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>

        <Toaster />
    </div>
</template>
