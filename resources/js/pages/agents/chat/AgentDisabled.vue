<script setup lang="ts">
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { AlertTriangle, Mail } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <AuthBase :title="t('agents.chat_errors.disabled_title')" :description="t('agents.chat_errors.disabled_description')">
        <div class="absolute top-4 end-4">
            <ThemeSwitcher />
        </div>

        <div class="mx-auto w-full max-w-lg space-y-8">
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-950/50">
                <div class="flex items-center space-x-4">
                    <div class="shrink-0">
                        <AlertTriangle class="h-8 w-8 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-amber-800 dark:text-amber-200">{{ t('agents.chat_errors.disabled_heading') }}</h3>
                        <p class="mt-2 text-base text-amber-700 dark:text-amber-300">
                            {{ t('agents.chat_errors.disabled_message') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-4 text-center">
                <p class="text-muted-foreground">
                    {{ t('agents.chat_errors.disabled_hint') }}
                </p>
            </div>

            <div class="space-y-4">
                <Button type="button" variant="outline" class="h-12 w-full text-base font-medium">
                    <Mail class="me-2 h-5 w-5" />
                    {{ t('agents.chat_errors.contact_admin') }}
                </Button>
            </div>

            <div class="pt-4 text-center">
                <p class="text-muted-foreground text-base">
                    <a href="/dashboard" class="text-primary hover:text-primary/80 font-semibold underline">
                        {{ t('agents.chat_errors.return_dashboard') }}
                    </a>
                </p>
            </div>
        </div>
    </AuthBase>
</template>
