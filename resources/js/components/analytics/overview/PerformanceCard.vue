<script setup lang="ts">
/** "Performance & usage" card of the analytics overview: response times, session length and token use. */
import type { AgentStats } from '@/api/analytics';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Coins, Zap } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    stats: AgentStats;
}>();

const { t } = useI18n();

const formatDuration = (seconds: number | null): string => {
    if (!seconds) return t('agents.analytics.na');
    const minutes = Math.floor(seconds / 60);
    const secs = Math.round(seconds % 60);
    if (minutes > 0) {
        return `${minutes}m ${secs}s`;
    }
    return `${secs}s`;
};
</script>

<template>
    <Card class="mb-6">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Zap class="h-5 w-5" />
                {{ t('agents.analytics.performance_usage') }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                <div>
                    <p class="text-muted-foreground mb-1 text-sm">{{ t('agents.analytics.avg_response_time') }}</p>
                    <p class="text-2xl font-bold">{{ stats.avg_response_time ?? 0 }}s</p>
                </div>
                <div>
                    <p class="text-muted-foreground mb-1 text-sm">{{ t('agents.analytics.first_response_time') }}</p>
                    <p class="text-2xl font-bold">{{ formatDuration(stats.avg_first_response_time ?? null) }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground mb-1 text-sm">{{ t('agents.analytics.avg_session_duration') }}</p>
                    <p class="text-2xl font-bold">{{ formatDuration(stats.avg_conversation_duration ?? null) }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground mb-1 text-sm">{{ t('agents.analytics.avg_messages_per_chat') }}</p>
                    <p class="text-2xl font-bold">{{ Number(stats.avg_messages_per_conversation ?? 0).toFixed(1) }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground mb-1 text-sm">{{ t('agents.analytics.unique_sessions') }}</p>
                    <p class="text-2xl font-bold">{{ (stats.unique_sessions ?? 0).toLocaleString() }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground mb-1 flex items-center gap-1 text-sm">
                        <Coins class="h-3.5 w-3.5" /> {{ t('agents.analytics.total_tokens') }}
                    </p>
                    <p class="text-2xl font-bold">{{ (stats.total_tokens_used ?? 0).toLocaleString() }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground mb-1 text-sm">{{ t('agents.analytics.avg_tokens_per_message') }}</p>
                    <p class="text-2xl font-bold">{{ Math.round(stats.avg_tokens_per_message ?? 0).toLocaleString() }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
