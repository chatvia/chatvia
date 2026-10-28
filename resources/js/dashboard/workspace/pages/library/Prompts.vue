<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { MessageSquareText, Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/ui/table';
import AppLayout from '@/dashboard/layouts/AppLayout.vue';
import WorkspaceLayout from '@/dashboard/workspace/layouts/Layout.vue';
import { type BreadcrumbItem } from '@/shared/types';

import PromptFormSheet, { type PromptRow } from './Prompts/PromptFormSheet.vue';

interface Props {
    prompts: PromptRow[];
}

const props = defineProps<Props>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.prompts.breadcrumb'), href: '/my/workspace/library/prompts' }];

const search = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.prompts;
    return props.prompts.filter((p) => p.name.toLowerCase().includes(q) || p.prompt.toLowerCase().includes(q));
});

const sheetOpen = ref(false);
const editing = ref<PromptRow | null>(null);

function openCreate(): void {
    editing.value = null;
    sheetOpen.value = true;
}

function openEdit(p: PromptRow): void {
    editing.value = p;
    sheetOpen.value = true;
}

function refreshPrompts(): void {
    router.reload({ only: ['prompts'] });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('workspace.prompts.title')" />

        <WorkspaceLayout width="wide">
            <div class="w-full space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <MessageSquareText class="size-6" />
                            {{ t('workspace.prompts.title') }}
                        </h1>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ t('workspace.prompts.description') }}
                        </p>
                    </div>
                    <Button @click="openCreate">
                        <Plus class="me-1 h-4 w-4" />
                        {{ t('workspace.prompts.add') }}
                    </Button>
                </div>

                <div class="relative max-w-sm">
                    <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="search" :placeholder="t('workspace.prompts.search_placeholder')" class="ps-9" />
                </div>

                <div v-if="prompts.length === 0" class="text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    {{ t('workspace.prompts.empty') }}
                </div>

                <div v-else-if="filtered.length === 0" class="text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    {{ t('workspace.prompts.no_results') }}
                </div>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="h-11 ps-5">{{ t('workspace.prompts.table.prompt') }}</TableHead>
                            <TableHead class="h-11 w-28">{{ t('workspace.prompts.table.agents') }}</TableHead>
                            <TableHead class="h-11 w-40 pe-5">{{ t('workspace.prompts.table.created') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="p in filtered" :key="p.id" class="hover:bg-muted/40 cursor-pointer" @click="openEdit(p)">
                            <TableCell class="py-4 ps-5">
                                <div class="flex items-center gap-4">
                                    <div class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                        <MessageSquareText class="text-muted-foreground h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ p.name }}</p>
                                        <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">{{ p.prompt }}</p>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="py-4 text-sm">{{ p.agents_count }}</TableCell>
                            <TableCell class="text-muted-foreground py-4 pe-5 text-sm">{{ p.created_at }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <PromptFormSheet v-model:open="sheetOpen" :prompt="editing" @changed="refreshPrompts" />
        </WorkspaceLayout>
    </AppLayout>
</template>
