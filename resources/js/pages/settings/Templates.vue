<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { LayoutTemplate, Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface TemplateRow {
    id: string;
    name: string;
    agents_count: number;
    created_at: string | null;
}

interface Props {
    templates: TemplateRow[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Templates', href: '/settings/templates' }];

const search = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.templates;
    return props.templates.filter((t) => t.name.toLowerCase().includes(q));
});

function openEdit(t: TemplateRow): void {
    router.visit(route('settings.templates.edit', { external_id: t.id }));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Templates" />

        <SettingsLayout width="wide">
            <div class="w-full space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <LayoutTemplate class="size-6" />
                            Templates
                        </h1>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Reusable agent setups. Build one here — appearance, widget, models, starters and prompt — then attach it to any agent.
                            Edit it once and every attached agent picks up the change.
                        </p>
                    </div>
                    <Button as-child>
                        <Link :href="route('settings.templates.create')">
                            <Plus class="mr-1 h-4 w-4" />
                            Add template
                        </Link>
                    </Button>
                </div>

                <div class="relative max-w-sm">
                    <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="search" placeholder="Search templates" class="pl-9" />
                </div>

                <div v-if="templates.length === 0" class="text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    No templates yet. Click "Add template" to create your first one.
                </div>

                <div v-else-if="filtered.length === 0" class="text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    No templates match your search.
                </div>

                <div v-else class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="h-11 pl-5">Template</TableHead>
                                <TableHead class="h-11 w-28">Agents</TableHead>
                                <TableHead class="h-11 w-40 pr-5">Created</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="t in filtered" :key="t.id" class="hover:bg-muted/40 cursor-pointer" @click="openEdit(t)">
                                <TableCell class="py-4 pl-5">
                                    <div class="flex items-center gap-4">
                                        <div class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                            <LayoutTemplate class="text-muted-foreground h-5 w-5" />
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <p class="truncate text-sm font-medium">{{ t.name }}</p>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="py-4 text-sm">{{ t.agents_count }}</TableCell>
                                <TableCell class="text-muted-foreground py-4 pr-5 text-sm">{{ t.created_at }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
