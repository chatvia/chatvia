<script setup lang="ts">
/**
 * A sidebar entry with child links (Analytics, Security, Knowledge Sources).
 * Expanded sidebar: a button with a chevron that shows or hides the links
 * below it (`v-model:open`). Collapsed to icons: the button opens the links in
 * a pop-out menu instead.
 *
 * By default the links come from `item.subItems`. The `#dropdown` and
 * `#submenu` slots replace them, for menus that build their own links.
 */
import AgentSidebarDropdownLink from '@/components/agents/layout/AgentSidebarDropdownLink.vue';
import AgentSidebarSubLink from '@/components/agents/layout/AgentSidebarSubLink.vue';
import type { AgentNavSubItem } from '@/composables/agents/layout/useAgentNavigation';
import { useActiveRoute } from '@/composables/agents/layout/useActiveRoute';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenuButton, SidebarMenuItem, SidebarMenuSub } from '@/components/ui/sidebar';
import { ChevronDown } from 'lucide-vue-next';
import type { Component } from 'vue';

defineProps<{
    agentId: string;
    item: {
        label: string;
        icon: Component;
        tooltip: string;
        subItems?: AgentNavSubItem[];
    };
}>();

const open = defineModel<boolean>('open', { required: true });

const { isActiveRoute } = useActiveRoute();
</script>

<template>
    <SidebarMenuItem>
        <!-- Collapsed sidebar: the links open in a pop-out menu -->
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <div class="hidden group-data-[collapsible=icon]:block">
                    <SidebarMenuButton :tooltip="item.tooltip" class="cursor-pointer">
                        <component :is="item.icon" />
                        <span>{{ item.label }}</span>
                    </SidebarMenuButton>
                </div>
            </DropdownMenuTrigger>
            <DropdownMenuContent side="right" align="start" :side-offset="5" :align-offset="0" class="w-56">
                <slot name="dropdown">
                    <AgentSidebarDropdownLink
                        v-for="subItem in item.subItems"
                        :key="subItem.id"
                        :href="subItem.url(agentId)"
                        :icon="subItem.icon"
                        :label="subItem.label"
                    />
                </slot>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Expanded sidebar: a toggle with the links listed below it -->
        <div class="block group-data-[collapsible=icon]:hidden">
            <SidebarMenuButton :tooltip="item.tooltip" class="cursor-pointer" @click="open = !open">
                <component :is="item.icon" />
                <span>{{ item.label }}</span>
                <ChevronDown class="ms-auto h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': open }" />
            </SidebarMenuButton>
        </div>
        <SidebarMenuSub v-if="open" class="mt-1 space-y-0.5 group-data-[collapsible=icon]:hidden">
            <slot name="submenu">
                <AgentSidebarSubLink
                    v-for="subItem in item.subItems"
                    :key="subItem.id"
                    :href="subItem.url(agentId)"
                    :icon="subItem.icon"
                    :label="subItem.label"
                    :active="isActiveRoute(subItem.route)"
                />
            </slot>
        </SidebarMenuSub>
    </SidebarMenuItem>
</template>
