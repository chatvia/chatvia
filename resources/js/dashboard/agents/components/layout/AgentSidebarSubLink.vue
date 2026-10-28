<script setup lang="ts">
/**
 * A link inside one of the agent sidebar's expanded submenus (Analytics,
 * Knowledge Sources, Security), with the inverted style for the active page.
 */
import { ACTIVE_LINK_CLASS } from '@/dashboard/agents/components/layout/sidebarClasses';
import { SidebarMenuSubButton, SidebarMenuSubItem } from '@/shared/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

defineProps<{
    href: string;
    icon: Component;
    label: string;
    active: boolean;
}>();
</script>

<template>
    <SidebarMenuSubItem>
        <SidebarMenuSubButton as-child :is-active="active" :class="ACTIVE_LINK_CLASS">
            <Link :href="href">
                <component :is="icon" class="h-4 w-4" />
                <span>{{ label }}</span>
            </Link>
        </SidebarMenuSubButton>
    </SidebarMenuSubItem>
</template>
