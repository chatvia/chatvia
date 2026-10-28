<script lang="ts" setup>
/**
 * The shell of every agent page: the permission-filtered sidebar, the top bar
 * with breadcrumbs, an optional page header, and the page itself in the
 * default slot. Also hosts the rename and delete dialogs the sidebar opens,
 * and the agent part of the onboarding tour.
 *
 * Every page with a header passes both `title` and `description`; the body
 * never repeats them. Slots: `default` (page content), `header-description`
 * (a description with a link) and `header-actions` (both in the page header,
 * so not shown with `hideHeader`).
 */
import AgentPageHeader from '@/dashboard/agents/components/layout/AgentPageHeader.vue';
import AgentSidebar from '@/dashboard/agents/components/layout/AgentSidebar.vue';
import AgentTopBar from '@/dashboard/agents/components/layout/AgentTopBar.vue';
import DeleteAgentDialog from '@/dashboard/agents/components/layout/DeleteAgentDialog.vue';
import RenameAgentDialog from '@/dashboard/agents/components/layout/RenameAgentDialog.vue';
import AgentTour from '@/dashboard/agents/components/onboarding/AgentTour.vue';
import { SidebarProvider } from '@/shared/ui/sidebar';
import { Toaster } from '@/shared/ui/sonner';
import type { AgentNavAction } from '@/dashboard/agents/composables/layout/useAgentNavigation';
import { useAgentSidebarOpen } from '@/dashboard/agents/composables/layout/useAgentSidebarOpen';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The agent fields the layout reads. Pages pass richer shapes (usually
 * AgentResourceData); anything with these fields works.
 */
interface AgentLayoutAgent {
    external_id: string;
    name: string;
    slug?: string | null;
    is_public?: boolean;
}

const props = withDefaults(
    defineProps<{
        agent: AgentLayoutAgent;
        title?: string;
        description?: string;
        hideHeader?: boolean;
        noPadding?: boolean;
        fullHeight?: boolean;
    }>(),
    {
        title: '',
        description: '',
        hideHeader: false,
        noPadding: false,
        fullHeight: false,
    },
);

const { t } = useI18n();

const { sidebarOpen } = useAgentSidebarOpen();

const pageTitle = computed(() => props.title || t('agents.nav.default_title'));

const showRenameDialog = ref(false);
const showDeleteDialog = ref(false);

function onSidebarAction(action: AgentNavAction): void {
    if (action === 'rename') showRenameDialog.value = true;
    if (action === 'delete') showDeleteDialog.value = true;
}
</script>

<template>
    <Toaster position="bottom-center" :duration="4000" />
    <Head :title="pageTitle" />

    <SidebarProvider v-model:open="sidebarOpen">
        <div class="bg-background flex min-h-screen w-full">
            <AgentSidebar :agent-id="agent.external_id" @toggle="sidebarOpen = !sidebarOpen" @action="onSidebarAction" />

            <!-- Main column: top bar, optional page header, page content -->
            <div class="flex min-w-0 flex-1 flex-col overflow-x-hidden">
                <AgentTopBar :agent-name="agent.name" />

                <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                    <AgentPageHeader v-if="!hideHeader" :title="pageTitle" :description="description" :is-public="Boolean(agent.is_public)">
                        <template v-if="$slots['header-description']" #description><slot name="header-description" /></template>
                        <template #actions><slot name="header-actions" /></template>
                    </AgentPageHeader>

                    <div class="min-w-0 flex-1" :class="[fullHeight ? 'overflow-hidden' : 'overflow-auto', noPadding ? '' : 'bg-muted/30']">
                        <div :class="['max-w-full min-w-0', noPadding ? 'h-full' : 'p-3 sm:p-4 lg:p-6', fullHeight ? 'h-full' : '']">
                            <slot />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteAgentDialog v-model:open="showDeleteDialog" :agent-id="agent.external_id" :agent-name="agent.name" />
        <RenameAgentDialog v-model:open="showRenameDialog" :agent-id="agent.external_id" :agent-name="agent.name" :slug="agent.slug" />
        <AgentTour />
    </SidebarProvider>
</template>
