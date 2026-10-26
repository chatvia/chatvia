<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Code, Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkspaceLayout from '@/layouts/workspace/Layout.vue';
import { toggle as toggleFunction } from '@/routes/integrations/functions';
import { type BreadcrumbItem } from '@/types';

import FunctionFormSheet, { type FunctionRow } from './Functions/FunctionFormSheet.vue';

interface Props {
    functions: FunctionRow[];
}

const props = defineProps<Props>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.functions.breadcrumb'), href: '/my/workspace/integrations/functions' }];

const search = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.functions;
    return props.functions.filter(
        (f) =>
            f.internal_name.toLowerCase().includes(q) || (f.display_name ?? '').toLowerCase().includes(q) || f.description.toLowerCase().includes(q),
    );
});

const sheetOpen = ref(false);
const editing = ref<FunctionRow | null>(null);

function openCreate(): void {
    editing.value = null;
    sheetOpen.value = true;
}

function openEdit(fn: FunctionRow): void {
    editing.value = fn;
    sheetOpen.value = true;
}

function toggleEnabled(fn: FunctionRow): void {
    router.post(toggleFunction.url(fn.id), {}, { preserveScroll: true, only: ['functions'] });
}

function refreshFunctions(): void {
    router.reload({ only: ['functions'] });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('workspace.functions.title')" />

        <WorkspaceLayout width="wide">
            <div class="w-full space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Code class="size-6" />
                            {{ t('workspace.functions.title') }}
                        </h1>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ t('workspace.functions.description') }}
                        </p>
                    </div>
                    <Button @click="openCreate">
                        <Plus class="me-1 h-4 w-4" />
                        {{ t('workspace.functions.add') }}
                    </Button>
                </div>

                <div class="relative max-w-sm">
                    <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="search" :placeholder="t('workspace.functions.search_placeholder')" class="ps-9" />
                </div>

                <div v-if="functions.length === 0" class="text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    {{ t('workspace.functions.empty') }}
                </div>

                <div v-else-if="filtered.length === 0" class="text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    {{ t('workspace.functions.no_results') }}
                </div>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="h-11 ps-5">{{ t('workspace.functions.table.name') }}</TableHead>
                            <TableHead class="h-11 w-28">{{ t('workspace.functions.table.method') }}</TableHead>
                            <TableHead class="h-11 w-28">{{ t('workspace.functions.table.agents') }}</TableHead>
                            <TableHead class="h-11 w-28 pe-5 text-end">{{ t('workspace.functions.table.enabled') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="fn in filtered" :key="fn.id" class="hover:bg-muted/40 cursor-pointer" @click="openEdit(fn)">
                            <TableCell class="py-4 ps-5">
                                <div class="flex items-center gap-4">
                                    <div class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                        <Code class="text-muted-foreground h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium" :class="{ 'font-mono': !fn.display_name }">
                                            {{ fn.display_name || fn.internal_name }}
                                        </p>
                                        <p v-if="fn.display_name" class="text-muted-foreground truncate font-mono text-xs">
                                            {{ fn.internal_name }}
                                        </p>
                                        <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">{{ fn.description }}</p>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="py-4">
                                <Badge variant="secondary" class="font-mono text-xs">{{ fn.webhook_method }}</Badge>
                            </TableCell>
                            <TableCell class="py-4 text-sm">{{ fn.agents_count }}</TableCell>
                            <TableCell class="py-4 pe-5 text-end" @click.stop>
                                <Switch :model-value="fn.is_enabled" @update:model-value="toggleEnabled(fn)" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <FunctionFormSheet v-model:open="sheetOpen" :func="editing" @changed="refreshFunctions" />
        </WorkspaceLayout>
    </AppLayout>
</template>
