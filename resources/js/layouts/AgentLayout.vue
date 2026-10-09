<script lang="ts" setup>
/**
 * The shell of every agent page: the permission-filtered sidebar, the top bar
 * with breadcrumbs, an optional page header, and the page itself in the
 * default slot. Also hosts the rename and delete dialogs the sidebar opens.
 *
 * Slots: `default` (page content), `header-icon` and `header-actions` (both in
 * the page header, so not shown with `hideHeader`).
 */
import AgentPageHeader from '@/components/agents/layout/AgentPageHeader.vue';
import AgentSidebar from '@/components/agents/layout/AgentSidebar.vue';
import AgentTopBar from '@/components/agents/layout/AgentTopBar.vue';
import DeleteAgentDialog from '@/components/agents/layout/DeleteAgentDialog.vue';
import RenameAgentDialog from '@/components/agents/layout/RenameAgentDialog.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import type { AgentNavAction } from '@/composables/agents/layout/useAgentNavigation';
import { useAgentSidebarOpen } from '@/composables/agents/layout/useAgentSidebarOpen';
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
    sub_domain?: string | null;
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
                        <template #icon><slot name="header-icon" /></template>
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
        <RenameAgentDialog v-model:open="showRenameDialog" :agent-id="agent.external_id" :agent-name="agent.name" :sub-domain="agent.sub_domain" />
    </SidebarProvider>
</template>
