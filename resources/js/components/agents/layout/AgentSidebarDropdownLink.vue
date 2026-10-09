<script setup lang="ts">
/**
 * A link in the pop-out menu a submenu opens when the agent sidebar is
 * collapsed to icons.
 */
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

defineProps<{
    href: string;
    icon: Component;
    label: string;
}>();
</script>

<template>
    <DropdownMenuItem as-child>
        <Link :href="href" class="flex items-center gap-2">
            <component :is="icon" class="h-4 w-4" />
            <span>{{ label }}</span>
        </Link>
    </DropdownMenuItem>
</template>
