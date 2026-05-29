<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { MessageSquareText, Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

import PromptFormSheet, { type PromptRow } from './Prompts/PromptFormSheet.vue';

interface Props {
    prompts: PromptRow[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Prompts', href: '/settings/prompts' }];

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
        <Head title="Prompts" />

        <SettingsLayout width="wide">
            <div class="w-full space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <MessageSquareText class="size-6" />
                            Prompts
                        </h1>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Reusable system prompts. Write one here, then attach it to any agent from its Persona page — edit it once and every
                            agent using it picks up the change.
                        </p>
                    </div>
                    <Button @click="openCreate">
                        <Plus class="mr-1 h-4 w-4" />
                        Add prompt
                    </Button>
                </div>

                <div class="relative max-w-sm">
                    <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="search" placeholder="Search prompts" class="pl-9" />
                </div>

                <div v-if="prompts.length === 0" class="text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    No prompts yet. Click "Add prompt" to create your first one.
                </div>

                <div v-else-if="filtered.length === 0" class="text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    No prompts match your search.
                </div>

                <div v-else class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="h-11 pl-5">Prompt</TableHead>
                                <TableHead class="h-11 w-28">Agents</TableHead>
                                <TableHead class="h-11 w-40 pr-5">Created</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="p in filtered" :key="p.id" class="hover:bg-muted/40 cursor-pointer" @click="openEdit(p)">
                                <TableCell class="py-4 pl-5">
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
                                <TableCell class="text-muted-foreground py-4 pr-5 text-sm">{{ p.created_at }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <PromptFormSheet v-model:open="sheetOpen" :prompt="editing" @changed="refreshPrompts" />
        </SettingsLayout>
    </AppLayout>
</template>
