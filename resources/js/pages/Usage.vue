<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Bot, Clock, Coins, FileText, Layers, Link2, Loader2, MessageSquare, Sparkles, Users, type LucideIcon } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

type UsageData = App.Identity.Workspace.Data.Resources.My.Usage.UsageResourceData;

const usageData = ref<UsageData | null>(null);
const isLoadingUsage = ref(true);

const fetchUsageData = async () => {
    try {
        isLoadingUsage.value = true;
        const response = await fetch(route('usage'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (response.ok) {
            usageData.value = await response.json();
        }
    } catch (error) {
        console.error('Failed to fetch usage data:', error);
    } finally {
        isLoadingUsage.value = false;
    }
};

interface Metric {
    key: string;
    label: string;
    icon: LucideIcon;
    used: number;
    limit: number;
    remaining: number;
    /** Capped metrics show a progress bar + "remaining"; uncapped ones show a note instead. */
    capped: boolean;
    note?: string;
}

const metrics = computed<Metric[]>(() => {
    const d = usageData.value;

    if (!d) {
        return [];
    }

    const all: Metric[] = [
        {
            key: 'messages',
            label: t('usage.label_messages'),
            icon: MessageSquare,
            used: d.messages.used,
            limit: d.messages.limit,
            remaining: d.messages.remaining,
            capped: true,
        },
        {
            key: 'tokens',
            label: t('usage.label_tokens'),
            icon: Coins,
            used: d.tokens.used,
            limit: 0,
            remaining: 0,
            capped: false,
            note: t('usage.tokens_note'),
        },
        {
            key: 'pages',
            label: t('usage.label_pages'),
            icon: FileText,
            used: d.pages.used,
            limit: d.pages.limit,
            remaining: d.pages.remaining,
            capped: true,
        },
        {
            key: 'minutes',
            label: t('usage.label_minutes'),
            icon: Clock,
            used: d.minutes.used,
            limit: d.minutes.limit,
            remaining: d.minutes.remaining,
            capped: true,
        },
        {
            key: 'links',
            label: t('usage.label_links'),
            icon: Link2,
            used: d.links.used,
            limit: d.links.limit,
            remaining: d.links.remaining,
            capped: true,
        },
        {
            key: 'agents',
            label: t('usage.label_agents'),
            icon: Bot,
            used: d.agents.used,
            limit: d.agents.limit,
            remaining: d.agents.remaining,
            capped: true,
        },
        {
            key: 'teams',
            label: t('usage.label_teams'),
            icon: Layers,
            used: d.teams.used,
            limit: d.teams.limit,
            remaining: d.teams.remaining,
            capped: true,
        },
        {
            key: 'members',
            label: t('usage.label_members'),
            icon: Users,
            used: d.members.used,
            limit: d.members.limit,
            remaining: d.members.remaining,
            capped: true,
        },
    ];

    if (!d.unlimited) {
        return all;
    }

    // Billing is turned off: there are no limits, and no message credits are
    // counted, so show plain usage and drop the two credit-cycle cards.
    return all.filter((m) => m.key !== 'messages' && m.key !== 'tokens').map((m) => ({ ...m, capped: false, note: t('usage.no_limit') }));
});

const percent = (m: Metric): number => (m.limit === 0 ? 0 : Math.min(100, Math.round((m.used / m.limit) * 100)));

// Stay on-theme: neutral bar by default, destructive only when near the cap.
const barClass = (pct: number): string => (pct >= 90 ? 'bg-destructive' : 'bg-primary');

onMounted(fetchUsageData);
</script>

<template>
    <AppLayout>
        <Head :title="t('usage.title')" />

        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8">
            <!-- Header -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('usage.title') }}</h1>
                    <p class="text-muted-foreground text-sm">{{ t('usage.subtitle') }}</p>
                </div>
                <Badge v-if="usageData && !usageData.unlimited" variant="secondary" class="h-fit w-fit gap-1.5 capitalize">
                    <Sparkles class="h-3.5 w-3.5" />
                    {{ t('usage.plan_badge', { plan: usageData.subscription.plan }) }}
                </Badge>
            </div>

            <!-- Loading -->
            <div v-if="isLoadingUsage" class="flex items-center justify-center py-16">
                <Loader2 class="text-muted-foreground h-6 w-6 animate-spin" />
            </div>

            <!-- Metrics -->
            <div v-else-if="usageData" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card v-for="m in metrics" :key="m.key">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">{{ m.label }}</CardTitle>
                        <component :is="m.icon" class="text-muted-foreground h-4 w-4 shrink-0" />
                    </CardHeader>
                    <CardContent class="space-y-2.5">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-2xl font-bold tracking-tight tabular-nums">{{ m.used.toLocaleString() }}</span>
                            <span v-if="m.capped" class="text-muted-foreground text-xs whitespace-nowrap">{{
                                t('usage.of', { limit: m.limit.toLocaleString() })
                            }}</span>
                        </div>

                        <template v-if="m.capped">
                            <div class="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="barClass(percent(m))"
                                    :style="{ width: `${percent(m)}%` }"
                                />
                            </div>
                            <p class="text-muted-foreground text-xs">{{ t('usage.remaining', { count: m.remaining.toLocaleString() }) }}</p>
                        </template>
                        <p v-else class="text-muted-foreground text-xs leading-relaxed">{{ m.note }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty / error -->
            <div v-else class="text-muted-foreground py-16 text-center text-sm">{{ t('usage.load_error') }}</div>
        </div>
    </AppLayout>
</template>
