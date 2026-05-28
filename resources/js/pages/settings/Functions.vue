<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Code, Plus, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

import FunctionFormSheet, { type FunctionRow } from './Functions/FunctionFormSheet.vue';

interface Props {
    functions: FunctionRow[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Functions', href: '/settings/functions' }];

const search = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.functions;
    return props.functions.filter((f) => f.name.toLowerCase().includes(q) || f.description.toLowerCase().includes(q));
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
    router.post(route('settings.functions.toggle', { external_id: fn.id }), {}, { preserveScroll: true, only: ['functions'] });
}

function refreshFunctions(): void {
    router.reload({ only: ['functions'] });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Functions" />

        <SettingsLayout width="wide">
            <div class="w-full space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Code class="size-6" />
                            Functions
                        </h1>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Webhook-backed tools the LLM can call. Define once, attach to any of your agents. When invoked, Chatvia POSTs the
                            parameters to your webhook and forwards the response back to the model.
                        </p>
                    </div>
                    <Button @click="openCreate">
                        <Plus class="mr-1 h-4 w-4" />
                        Add function
                    </Button>
                </div>

                <div class="relative max-w-sm">
                    <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="search" placeholder="Search functions" class="pl-9" />
                </div>

                <div v-if="functions.length === 0" class="text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    No functions yet. Click "Add function" to create your first one.
                </div>

                <div v-else-if="filtered.length === 0" class="text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    No functions match your search.
                </div>

                <div v-else class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="h-11 pl-5">Function name</TableHead>
                                <TableHead class="h-11 w-28">Method</TableHead>
                                <TableHead class="h-11 w-28">Agents</TableHead>
                                <TableHead class="h-11 w-28 pr-5 text-right">Enabled</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="fn in filtered" :key="fn.id" class="hover:bg-muted/40 cursor-pointer" @click="openEdit(fn)">
                                <TableCell class="py-4 pl-5">
                                    <div class="flex items-center gap-4">
                                        <div class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                            <Code class="text-muted-foreground h-5 w-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-mono text-sm font-medium">{{ fn.name }}</p>
                                            <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">{{ fn.description }}</p>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="py-4">
                                    <Badge variant="secondary" class="font-mono text-xs">{{ fn.webhook_method }}</Badge>
                                </TableCell>
                                <TableCell class="py-4 text-sm">{{ fn.agents_count }}</TableCell>
                                <TableCell class="py-4 pr-5 text-right" @click.stop>
                                    <Switch :model-value="fn.enabled" @update:model-value="toggleEnabled(fn)" />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <FunctionFormSheet v-model:open="sheetOpen" :func="editing" @changed="refreshFunctions" />
        </SettingsLayout>
    </AppLayout>
</template>
