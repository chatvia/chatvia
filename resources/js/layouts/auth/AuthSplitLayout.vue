<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const page = usePage();
const name = page.props.name;
const quote = page.props.quote;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
        <div class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-e">
            <div class="absolute inset-0 bg-zinc-900" />
            <Link :href="route('home')" class="relative z-20 flex items-center text-lg font-medium">
                <AppLogoIcon class="me-2 size-16" />
                {{ name }}
            </Link>
            <!-- Added beautiful centered title -->
            <div class="relative z-20 flex flex-1 items-center justify-center">
                <div class="text-center">
                    <i18n-t keypath="auth.split.headline" tag="h1" class="text-4xl font-bold tracking-tight text-white" scope="global">
                        <template #brand>
                            <span class="bg-gradient-to-r from-blue-400 to-purple-500 bg-clip-text text-transparent">ChatGPT</span>
                        </template>
                    </i18n-t>
                    <i18n-t keypath="auth.split.subheadline" tag="p" class="mt-4 text-lg text-neutral-300" scope="global">
                        <template #data>
                            <span class="font-semibold text-white">{{ t('auth.split.your_data') }}</span>
                        </template>
                    </i18n-t>
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <span class="inline-flex items-center rounded-full bg-blue-900/30 px-3 py-1 text-sm text-blue-300">
                            <svg class="me-1.5 h-3 w-3" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" />
                            </svg>
                            {{ t('auth.split.secure_data') }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-purple-900/30 px-3 py-1 text-sm text-purple-300">
                            <svg class="me-1.5 h-3 w-3" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" />
                            </svg>
                            {{ t('auth.split.custom_gpt') }}
                        </span>
                    </div>
                </div>
            </div>
            <div v-if="quote" class="relative z-20 mt-auto">
                <blockquote class="space-y-2">
                    <p class="text-lg">&ldquo;&rdquo;</p>
                    <footer class="text-sm text-neutral-300"></footer>
                </blockquote>
            </div>
        </div>
        <div class="lg:p-8">
            <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">{{ title }}</h1>
                    <p class="text-muted-foreground text-sm" v-if="description">{{ description }}</p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
