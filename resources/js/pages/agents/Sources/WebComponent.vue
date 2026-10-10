<script setup lang="ts">
/**
 * Website sources of an agent. A website goes through four stages, each with
 * its own card: crawling (pages are being discovered) → ready for training
 * (pick websites and train them) → training → trained.
 * Status changes arrive live over Reverb.
 */
import AttachWebsiteDialog from '@/components/common/AttachWebsiteDialog.vue';
import InfoBanner from '@/components/common/InfoBanner.vue';
import DeleteSourceDialog from '@/components/agents/sources/DeleteSourceDialog.vue';
import CrawledWebsitesCard from '@/components/agents/sources/web/CrawledWebsitesCard.vue';
import CrawlingWebsitesCard from '@/components/agents/sources/web/CrawlingWebsitesCard.vue';
import TrainedWebsitesCard from '@/components/agents/sources/web/TrainedWebsitesCard.vue';
import TrainingWebsitesCard from '@/components/agents/sources/web/TrainingWebsitesCard.vue';
import WebsiteCrawlerCard from '@/components/agents/sources/web/WebsiteCrawlerCard.vue';
import WebsiteLinksDialog from '@/components/agents/sources/web/WebsiteLinksDialog.vue';
import { useDiscoveredLinks } from '@/composables/agents/sources/web/useDiscoveredLinks';
import { useWebsiteDeletion } from '@/composables/agents/sources/web/useWebsiteDeletion';
import { useWebsiteLinksDialog } from '@/composables/agents/sources/web/useWebsiteLinksDialog';
import { useWebsiteLiveUpdates } from '@/composables/agents/sources/web/useWebsiteLiveUpdates';
import { useWebSources } from '@/composables/agents/sources/web/useWebSources';
import { useWebsiteTraining } from '@/composables/agents/sources/web/useWebsiteTraining';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}>();

const { t } = useI18n();
const agentId = props.agent.external_id;

const sources = useWebSources(agentId);
const links = useDiscoveredLinks(agentId, sources);
const training = useWebsiteTraining(agentId, sources);
const linksDialog = useWebsiteLinksDialog(agentId, sources);
const deletion = useWebsiteDeletion(agentId, sources, training);
useWebsiteLiveUpdates(agentId, { sources, links, training, linksDialog });

const { crawlingSources, crawledSources, trainingSources, completedSources, isLoadingSources, isInitialLoad, fetchWebSources } = sources;
const { isDialogOpen: isDeleteDialogOpen, target: websiteToDelete, description: deleteDescription, isUnlinking, isDeleting } = deletion;

const isAttachDialogOpen = ref(false);

const reload = () => {
    fetchWebSources();
};

onMounted(reload);
</script>

<template>
    <div class="flex justify-center">
        <div class="w-full max-w-6xl space-y-6">
            <InfoBanner
                :title="t('agents.sources.web.monitoring_title')"
                :message="t('agents.sources.web.monitoring_message')"
                storage-key="smart-monitoring-banner-dismissed"
                variant="info"
            />

            <WebsiteCrawlerCard :agent-id="agentId" @added="fetchWebSources()" />

            <CrawlingWebsitesCard v-if="crawlingSources.length > 0" :sources="crawlingSources" @delete="deletion.confirm" />

            <CrawledWebsitesCard
                v-if="crawledSources.length > 0"
                :sources="crawledSources"
                :training="training"
                :links="links"
                @delete="deletion.confirm"
            />

            <TrainingWebsitesCard
                v-if="trainingSources.length > 0"
                :sources="trainingSources"
                @view-links="linksDialog.open"
                @delete="deletion.confirm"
            />

            <TrainedWebsitesCard
                :sources="completedSources"
                :links="links"
                :is-loading="isLoadingSources && isInitialLoad"
                @attach="isAttachDialogOpen = true"
                @view-links="linksDialog.open"
                @delete="deletion.confirm"
            />

            <DeleteSourceDialog
                v-if="websiteToDelete"
                v-model:open="isDeleteDialogOpen"
                :title="websiteToDelete.name"
                :agents-count="websiteToDelete.agents_count"
                :description="deleteDescription"
                :shared-prefix="t('agents.sources.web.delete_shared_prefix')"
                :shared-suffix="t('agents.sources.web.delete_shared_suffix')"
                :is-unlinking="isUnlinking"
                :is-deleting="isDeleting"
                @unlink="deletion.unlink"
                @delete="deletion.remove"
            />

            <WebsiteLinksDialog :dialog="linksDialog" />
        </div>
    </div>

    <AttachWebsiteDialog v-model:open="isAttachDialogOpen" :agent-id="agentId" @website-attached="reload" />
</template>
