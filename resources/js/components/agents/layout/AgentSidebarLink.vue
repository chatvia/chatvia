<script setup lang="ts">
/**
 * A top-level link in the agent sidebar: icon and label, a tooltip when the
 * sidebar is collapsed to icons, and the inverted style for the active page.
 */
import { ACTIVE_LINK_CLASS } from '@/components/agents/layout/sidebarClasses';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

defineProps<{
    href: string;
    icon: Component;
    label: string;
    tooltip: string;
    active: boolean;
}>();
</script>

<template>
    <SidebarMenuButton as-child :is-active="active" :tooltip="tooltip" :class="ACTIVE_LINK_CLASS">
        <Link :href="href">
            <component :is="icon" />
            <span>{{ label }}</span>
        </Link>
    </SidebarMenuButton>
</template>
