<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Code, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

import AddFunctionModal from './Functions/AddFunctionModal.vue';

interface FunctionRow {
    id: string;
    name: string;
    description: string;
    webhook_url: string;
    webhook_method: string;
    auth_type: string;
    enabled: boolean;
    created_at: string | null;
    agents_count: number;
}

interface Props {
    functions: FunctionRow[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Functions', href: '/settings/functions' }];

const addOpen = ref(false);

const remove = (fn: FunctionRow) => {
    if (!confirm(`Delete "${fn.name}"? It will be detached from any agents using it.`)) return;
    router.delete(route('settings.functions.destroy', { external_id: fn.id }), { preserveScroll: true });
};
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
                    <Button @click="addOpen = true">
                        <Plus class="mr-1 h-4 w-4" />
                        Add function
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Your functions</CardTitle>
                        <CardDescription>
                            Attach a function to an agent from the agent's "Functions" page in the sidebar, or via
                            <code class="text-xs">POST /api/v1/agents/{id}/functions</code>.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="functions.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                            No functions yet. Click "Add function" to create your first one.
                        </div>
                        <ul v-else class="divide-border divide-y">
                            <li v-for="fn in functions" :key="fn.id" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-mono text-sm font-medium">{{ fn.name }}</p>
                                    <p class="text-muted-foreground mt-0.5 truncate text-xs">{{ fn.description }}</p>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        {{ fn.webhook_method }} {{ fn.webhook_url }} · auth: {{ fn.auth_type }} · attached to
                                        {{ fn.agents_count }} agent{{ fn.agents_count === 1 ? '' : 's' }}
                                    </p>
                                </div>
                                <Button variant="ghost" size="sm" @click="remove(fn)">
                                    <Trash2 class="size-4 text-red-600" />
                                </Button>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <AddFunctionModal v-model:open="addOpen" />
        </SettingsLayout>
    </AppLayout>
</template>
