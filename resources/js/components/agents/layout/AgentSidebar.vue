<script setup lang="ts">
/**
 * The agent area's left sidebar: logo and collapse controls, the top links,
 * the permission-filtered management groups and the owner-only Danger Zone.
 * Rename and delete only emit `action`; the layout owns those dialogs.
 * Collapsing is driven by the surrounding SidebarProvider; the expand button
 * shown while collapsed emits `toggle`.
 */
import { fetchSourceStats, type SourceStats } from '@/api/sourceStats';
import AgentSidebarDropdownLink from '@/components/agents/layout/AgentSidebarDropdownLink.vue';
import AgentSidebarLink from '@/components/agents/layout/AgentSidebarLink.vue';
import AgentSidebarSubLink from '@/components/agents/layout/AgentSidebarSubLink.vue';
import AgentSidebarSubmenu from '@/components/agents/layout/AgentSidebarSubmenu.vue';
import AgentSourcesMenu from '@/components/AgentSourcesMenu.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { useActiveRoute } from '@/composables/agents/layout/useActiveRoute';
import { type AgentNavAction, useAgentNavigation } from '@/composables/agents/layout/useAgentNavigation';
import { Link } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agentId: string;
}>();

const emit = defineEmits<{
    toggle: [];
    action: [action: AgentNavAction];
}>();

const { t } = useI18n();
const { isActiveRoute } = useActiveRoute();
const { topItems, managementGroups, dangerZoneItems } = useAgentNavigation();

// Which submenus start expanded: the one holding the current page.
// Ziggy's `*` only matches after the literal prefix ('agents.analytics.*' does
// not match 'agents.analytics' itself), so the bare name is checked as well.
// Functions and Connectors sit under `agents.sources.*` but are top-level
// items, so they do not open Knowledge Sources.
const submenuOpen = reactive<Record<string, boolean>>({
    analytics: isActiveRoute('agents.analytics') || isActiveRoute('agents.analytics.*'),
    security: isActiveRoute('agents.identity') || isActiveRoute('agents.security.rate-limit'),
    knowledge:
        (isActiveRoute('agents.sources') || isActiveRoute('agents.sources.*')) &&
        !isActiveRoute('agents.sources.functions.*') &&
        !isActiveRoute('agents.sources.connectors.*'),
});

// Per-type source counts shown next to the Knowledge Sources links. Optional:
// the menu works without them.
const sourceStats = ref<SourceStats | null>(null);

onMounted(async () => {
    try {
        sourceStats.value = await fetchSourceStats(props.agentId);
    } catch (error) {
        console.error('Failed to fetch source stats:', error);
    }
});

const agentRoute = (name: string): string => route(name, { agent: props.agentId });
</script>

<template>
    <Sidebar collapsible="icon" class="border-e">
        <!-- Logo and collapse controls -->
        <div class="border-border border-b">
            <!-- Expanded: logo, with the collapse trigger on the other side -->
            <div class="flex h-16 items-center justify-between px-4 group-data-[collapsible=icon]:hidden">
                <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-2 overflow-hidden">
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center">
                        <AppLogoIcon class="h-8 w-8" />
                    </div>
                </Link>

                <SidebarTrigger class="hidden flex-shrink-0 md:flex" />
            </div>

            <!-- Collapsed: the whole header area expands the sidebar -->
            <button
                @click="emit('toggle')"
                class="hover:bg-accent hidden h-16 w-full cursor-pointer items-center justify-center transition-colors group-data-[collapsible=icon]:flex"
                :aria-label="t('agents.nav.expand_sidebar')"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="h-4 w-4"
                >
                    <rect width="18" height="18" x="3" y="3" rx="2" />
                    <path d="M9 3v18" />
                    <path d="m14 9 3 3-3 3" />
                </svg>
            </button>
        </div>

        <SidebarContent class="!gap-3 overflow-y-auto">
            <!-- Top links: Playground, Setup, Deploy -->
            <SidebarGroup class="py-0 pt-2">
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in topItems" :key="item.id">
                        <AgentSidebarLink
                            :href="agentRoute(item.route)"
                            :icon="item.icon"
                            :label="item.label"
                            :tooltip="item.tooltip"
                            :active="isActiveRoute(item.route)"
                        />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Management groups: Insights, Training, Access, Settings -->
            <SidebarGroup v-for="group in managementGroups" :key="group.id" class="py-0">
                <SidebarGroupLabel class="mb-1 h-6 px-2">{{ group.label }}</SidebarGroupLabel>
                <SidebarMenu>
                    <template v-for="item in group.items" :key="item.id">
                        <AgentSidebarSubmenu v-if="item.kind === 'submenu'" v-model:open="submenuOpen[item.id]" :agent-id="agentId" :item="item" />

                        <AgentSidebarSubmenu v-else-if="item.kind === 'sources'" v-model:open="submenuOpen[item.id]" :agent-id="agentId" :item="item">
                            <template #dropdown>
                                <AgentSourcesMenu :agent-id="agentId" :source-stats="sourceStats" variant="dropdown" :is-active-route="isActiveRoute">
                                    <template #default="{ item: source, label }">
                                        <AgentSidebarDropdownLink :href="agentRoute(source.route)" :icon="source.icon" :label="label" />
                                    </template>
                                </AgentSourcesMenu>
                            </template>
                            <template #submenu>
                                <AgentSourcesMenu :agent-id="agentId" :source-stats="sourceStats" variant="submenu" :is-active-route="isActiveRoute">
                                    <template #default="{ item: source, label, isActive }">
                                        <AgentSidebarSubLink :href="agentRoute(source.route)" :icon="source.icon" :label="label" :active="isActive" />
                                    </template>
                                </AgentSourcesMenu>
                            </template>
                        </AgentSidebarSubmenu>

                        <SidebarMenuItem v-else>
                            <SidebarMenuButton
                                v-if="item.kind === 'action'"
                                @click="emit('action', item.action)"
                                :tooltip="item.tooltip"
                                class="cursor-pointer hover:bg-neutral-100 dark:hover:bg-neutral-800"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.label }}</span>
                            </SidebarMenuButton>
                            <AgentSidebarLink
                                v-else
                                :href="agentRoute(item.route)"
                                :icon="item.icon"
                                :label="item.label"
                                :tooltip="item.tooltip"
                                :active="isActiveRoute(item.route)"
                            />
                        </SidebarMenuItem>
                    </template>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Danger Zone, pinned to the bottom (mt-auto) when the sidebar is short. Owners only. -->
            <div v-if="dangerZoneItems.length" class="mt-auto">
                <div class="px-2 py-2">
                    <div class="border-border border-t"></div>
                </div>

                <SidebarGroup class="py-0">
                    <SidebarGroupLabel class="text-destructive mb-1 h-6 px-2">{{ t('agents.nav.groups.danger_zone') }}</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in dangerZoneItems" :key="item.id">
                            <SidebarMenuButton
                                @click="emit('action', item.action)"
                                :tooltip="item.tooltip"
                                class="text-destructive hover:text-destructive hover:bg-destructive/10 cursor-pointer"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.label }}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </div>
        </SidebarContent>
    </Sidebar>
</template>
