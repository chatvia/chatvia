<script lang="ts" setup>
/**
 * Topics analytics: which topics conversations are about, over time and in
 * total, plus suggestions for topics the agent does not have yet and a
 * manager to attach, create or remove topics. Shown only when the owner
 * switched the analysis on for the agent.
 */
import AnalyticsErrorState from '@/components/analytics/AnalyticsErrorState.vue';
import AnalyticsFeatureGate from '@/components/analytics/AnalyticsFeatureGate.vue';
import { useAnalyticsFeature } from '@/components/analytics/useAnalyticsFeature';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { LoaderCircle } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DismissSuggestionDialog from './topics/components/DismissSuggestionDialog.vue';
import ManageTopicsDialog from './topics/components/ManageTopicsDialog.vue';
import SuggestedTopicsCard from './topics/components/SuggestedTopicsCard.vue';
import TopicsChartCard from './topics/components/TopicsChartCard.vue';
import TopicsInfoDialog from './topics/components/TopicsInfoDialog.vue';
import { useTopicSuggestions } from './topics/useTopicSuggestions';
import { useTopicTrends } from './topics/useTopicTrends';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    // Per-agent on/off switch (Setup → Settings).
    enabled: boolean;
    // Master switch: when the chat log is off there are no stored messages to
    // analyze, so analytics can't be enabled until it's turned back on.
    chatlogEnabled: boolean;
}

const props = defineProps<Props>();

const { t } = useI18n();

const agentId = () => props.agent.external_id;

const { loading, topicsData, error, fetchTopicsData } = useTopicTrends(agentId);

const { suggestions, promotingLabel, dismissTarget, dismissDialogOpen, dismissing, fetchSuggestions, promoteSuggestion, askDismiss, confirmDismiss } =
    useTopicSuggestions(agentId, fetchTopicsData);

const { isEnabled, enabling, isActive, enable } = useAnalyticsFeature({
    agentId,
    feature: 'topics',
    enabled: props.enabled,
    chatlogEnabled: () => props.chatlogEnabled,
    onEnabled: () => {
        fetchTopicsData();
        fetchSuggestions();
    },
});

const activeView = ref<'graph' | 'pie'>('graph');
const showTopicsDialog = ref(false);
const showInfoDialog = ref(false);

onMounted(() => {
    if (isActive.value) {
        fetchTopicsData();
        fetchSuggestions();
    }
});
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.topics_index.title')" :description="t('agents.topics_index.description')">
        <AnalyticsFeatureGate
            :enabled="isEnabled"
            :chatlog-enabled="chatlogEnabled"
            :enabling="enabling"
            :disabled-title="t('agents.topics_index.disabled_title')"
            :disabled-description="t('agents.topics_index.disabled_description')"
            :blocked-reason="t('agents.topics_index.blocked_reason')"
            @enable="enable"
        >
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
            </div>

            <AnalyticsErrorState v-else-if="error" :message="error" :retry-label="t('agents.topics_index.try_again')" @retry="fetchTopicsData" />

            <!-- Content -->
            <div v-else-if="topicsData">
                <SuggestedTopicsCard
                    v-if="suggestions.length > 0"
                    :suggestions="suggestions"
                    :promoting-label="promotingLabel"
                    @promote="promoteSuggestion"
                    @dismiss="askDismiss"
                />

                <TopicsChartCard
                    v-model:view="activeView"
                    :topics-data="topicsData"
                    @show-info="showInfoDialog = true"
                    @view-all="showTopicsDialog = true"
                />
            </div>

            <ManageTopicsDialog v-model:open="showTopicsDialog" :agent-id="agent.external_id" :reload-trends="fetchTopicsData" />

            <TopicsInfoDialog v-model:open="showInfoDialog" />

            <DismissSuggestionDialog v-model:open="dismissDialogOpen" :label="dismissTarget" :dismissing="dismissing" @confirm="confirmDismiss" />
        </AnalyticsFeatureGate>
    </AgentLayout>
</template>
