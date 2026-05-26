<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Code, Plus, Trash2 } from 'lucide-vue-next';

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

const exampleSchema = `{
  "type": "object",
  "properties": {
    "order_id": { "type": "string" }
  },
  "required": ["order_id"]
}`;

const form = useForm({
    name: '',
    description: '',
    parameters_schema: exampleSchema,
    webhook_url: '',
    webhook_method: 'POST' as 'POST' | 'GET',
    auth_type: 'none' as 'none' | 'bearer' | 'header' | 'hmac',
    auth_value: '',
});

const submit = () => {
    form.post(route('settings.functions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.parameters_schema = exampleSchema;
            form.webhook_method = 'POST';
            form.auth_type = 'none';
        },
    });
};

const remove = (fn: FunctionRow) => {
    if (!confirm(`Delete "${fn.name}"? It will be detached from any agents using it.`)) return;
    router.delete(route('settings.functions.destroy', { external_id: fn.id }), { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Functions" />

        <SettingsLayout width="wide">
            <div class="flex flex-col space-y-8">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Code class="size-6" />
                        Functions
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Webhook-backed tools the LLM can call. Define once, attach to any of your agents. When invoked, Chatvia POSTs the parameters
                        to your webhook and forwards the response back to the model.
                    </p>
                </div>

                <Separator />

                <Card>
                    <CardHeader>
                        <CardTitle>Add a function</CardTitle>
                        <CardDescription>
                            The `name` and `description` are what the LLM sees when deciding whether to call this tool. Be specific.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="name">Name</Label>
                                <Input id="name" v-model="form.name" placeholder="get_order_status" required />
                                <p class="text-muted-foreground text-xs">Letters, numbers, underscore. Must start with a letter.</p>
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="description">Description</Label>
                                <Input
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Look up the current status of a customer's order by id."
                                    required
                                />
                                <InputError :message="form.errors.description" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="parameters_schema">Parameters (JSON schema)</Label>
                                <Textarea id="parameters_schema" v-model="form.parameters_schema" class="font-mono text-xs" rows="8" />
                                <InputError :message="form.errors.parameters_schema" />
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div class="col-span-2 grid gap-2">
                                    <Label for="webhook_url">Webhook URL</Label>
                                    <Input id="webhook_url" v-model="form.webhook_url" type="url" required />
                                    <InputError :message="form.errors.webhook_url" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="webhook_method">Method</Label>
                                    <select
                                        id="webhook_method"
                                        v-model="form.webhook_method"
                                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                    >
                                        <option value="POST">POST</option>
                                        <option value="GET">GET</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-2">
                                    <Label for="auth_type">Auth</Label>
                                    <select
                                        id="auth_type"
                                        v-model="form.auth_type"
                                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                    >
                                        <option value="none">None</option>
                                        <option value="bearer">Bearer token</option>
                                        <option value="header">Custom header</option>
                                        <option value="hmac">HMAC signature</option>
                                    </select>
                                </div>
                                <div v-if="form.auth_type !== 'none'" class="grid gap-2">
                                    <Label for="auth_value">Value</Label>
                                    <Input id="auth_value" v-model="form.auth_value" type="password" autocomplete="off" />
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <Button type="submit" :disabled="form.processing">
                                    <Plus class="mr-2 size-4" />
                                    Add function
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

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
                            No functions yet. Add one above.
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
        </SettingsLayout>
    </AppLayout>
</template>
