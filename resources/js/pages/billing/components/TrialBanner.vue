<script lang="ts" setup>
// Shown on the billing page while the workspace is on a free trial without a
// subscription: which plan the trial runs on and when it ends.
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Zap } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    planName: string;
    trialEndsAt: string | null;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="mb-8">
        <Card
            class="relative overflow-hidden border-emerald-200 bg-gradient-to-br from-emerald-50 via-teal-50 to-cyan-50 dark:border-emerald-800 dark:from-emerald-950/30 dark:via-teal-950/30 dark:to-cyan-950/30"
        >
            <div
                class="absolute inset-0 bg-gradient-to-r from-emerald-400/10 via-teal-400/10 to-cyan-400/10 opacity-70 dark:from-emerald-400/5 dark:via-teal-400/5 dark:to-cyan-400/5"
            ></div>
            <CardContent class="relative z-10 p-6">
                <div class="flex flex-col items-start justify-between gap-4 sm:flex-row">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 shadow-sm dark:from-emerald-400 dark:to-teal-500"
                        >
                            <Zap class="h-5 w-5 text-white" />
                        </div>
                        <div>
                            <h3
                                class="bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text font-semibold text-transparent dark:from-emerald-400 dark:to-teal-400"
                            >
                                {{ t('billing.index.trial.heading') }}
                            </h3>
                            <p class="mt-1 text-sm text-emerald-600/70 dark:text-emerald-300/70">
                                {{ t('billing.index.trial.ends_on', { plan: planName }) }}
                                <span class="font-semibold">{{ trialEndsAt }}</span>
                            </p>
                            <p class="mt-1 text-sm text-emerald-600/70 dark:text-emerald-300/70">
                                {{ t('billing.index.trial.auto_start', { date: trialEndsAt }) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Badge variant="outline" class="w-fit border-emerald-500 text-emerald-700 dark:text-emerald-300">
                            {{ t('billing.index.trial.badge') }}
                        </Badge>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
