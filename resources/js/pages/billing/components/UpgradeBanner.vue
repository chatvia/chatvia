<script lang="ts" setup>
// Nudges a workspace with neither a subscription nor a trial towards the plans page.
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { Star, Zap } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <div class="mb-8">
        <Card
            class="relative overflow-hidden border-indigo-200 bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 dark:border-indigo-800 dark:from-indigo-950/30 dark:via-purple-950/30 dark:to-pink-950/30"
        >
            <div
                class="absolute inset-0 bg-gradient-to-r from-indigo-400/10 via-purple-400/10 to-pink-400/10 opacity-70 dark:from-indigo-400/5 dark:via-purple-400/5 dark:to-pink-400/5"
            ></div>
            <div
                class="absolute -inset-[100%] animate-[spin_8s_linear_infinite] bg-gradient-to-r from-transparent via-indigo-400/20 to-transparent dark:via-indigo-400/10"
            ></div>
            <CardContent class="relative z-10 p-6">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 shadow-sm dark:from-indigo-400 dark:to-purple-500"
                        >
                            <Zap class="h-5 w-5 text-white" />
                        </div>
                        <div>
                            <h3
                                class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text font-semibold text-transparent dark:from-indigo-400 dark:to-purple-400"
                            >
                                {{ t('billing.index.upgrade.heading') }}
                            </h3>
                            <p class="text-sm text-indigo-600/70 dark:text-indigo-300/70">
                                {{ t('billing.index.upgrade.description') }}
                            </p>
                        </div>
                    </div>
                    <Button
                        size="sm"
                        class="gap-2 border-0 bg-gradient-to-r from-indigo-600 to-purple-600 whitespace-nowrap text-white shadow-sm hover:from-indigo-700 hover:to-purple-700"
                        as-child
                    >
                        <Link :href="route('billing.packages')">
                            <Star class="h-4 w-4" />
                            {{ t('billing.common.view_plans') }}
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
