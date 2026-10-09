<script lang="ts" setup>
// The billing page's "usage & limits" card: messages, pages, agents, links and
// minutes against the plan's limits.
import type { Usage } from '@/api/usage';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Bot, FileText, Link2, Loader2, MessageSquare, Timer } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import UsageMeter from './UsageMeter.vue';

defineProps<{
    usageData: Usage | null;
    isLoading: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-lg">{{ t('billing.index.usage.title') }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div v-if="isLoading" class="flex items-center justify-center py-8">
                <Loader2 class="text-muted-foreground h-6 w-6 animate-spin" />
            </div>

            <div v-else-if="usageData" class="space-y-4">
                <UsageMeter
                    v-if="usageData.messages"
                    :icon="MessageSquare"
                    icon-class="text-blue-500"
                    :label="t('billing.index.usage.messages')"
                    :stat="usageData.messages"
                />
                <UsageMeter
                    v-if="usageData.pages"
                    :icon="FileText"
                    icon-class="text-green-500"
                    :label="t('billing.index.usage.pages')"
                    :stat="usageData.pages"
                />
                <UsageMeter
                    v-if="usageData.agents"
                    :icon="Bot"
                    icon-class="text-purple-500"
                    :label="t('billing.index.usage.agents')"
                    :stat="usageData.agents"
                    :localize="false"
                />
                <UsageMeter
                    v-if="usageData.links"
                    :icon="Link2"
                    icon-class="text-orange-500"
                    :label="t('billing.index.usage.links')"
                    :stat="usageData.links"
                />
                <UsageMeter
                    v-if="usageData.minutes"
                    :icon="Timer"
                    icon-class="text-cyan-500"
                    :label="t('billing.index.usage.minutes')"
                    :stat="usageData.minutes"
                />
            </div>

            <div v-else class="py-4 text-center">
                <p class="text-muted-foreground text-sm">{{ t('billing.index.usage.load_error') }}</p>
            </div>
        </CardContent>
    </Card>
</template>
