<script setup lang="ts">
import ThemeSwitcher from '@/shared/components/ThemeSwitcher.vue';
import { Button } from '@/shared/ui/button';
import AuthBase from '@/shared/layouts/AuthLayout.vue';
import { Mail, Search } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <AuthBase :title="t('agents.chat_errors.not_found_title')" :description="t('agents.chat_errors.not_found_description')">
        <div class="absolute end-4 top-4">
            <ThemeSwitcher />
        </div>

        <div class="mx-auto w-full max-w-lg space-y-8">
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-950/50">
                <div class="flex items-center gap-4">
                    <div class="shrink-0">
                        <Search class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">{{ t('agents.chat_errors.not_found_heading') }}</h3>
                        <p class="mt-2 text-base text-blue-700 dark:text-blue-300">
                            {{ t('agents.chat_errors.not_found_message') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-4 text-center">
                <p class="text-muted-foreground">{{ t('agents.chat_errors.not_found_hint') }}</p>
            </div>

            <div class="space-y-4">
                <Button type="button" variant="outline" class="h-12 w-full text-base font-medium">
                    <Mail class="me-2 h-5 w-5" />
                    {{ t('agents.chat_errors.contact_admin') }}
                </Button>
            </div>
        </div>
    </AuthBase>
</template>
