<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import JsonCodeEditor from '@/components/ui/JsonCodeEditor.vue';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { AlignLeft, ExternalLink, Hash, Key, KeyRound, LoaderCircle, Lock, Plus, Send, Shield, Webhook } from 'lucide-vue-next';
import { watch } from 'vue';

interface Props {
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

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
    webhook_method: 'POST' as 'POST' | 'GET' | 'PATCH' | 'DELETE',
    auth_type: 'none' as 'none' | 'bearer' | 'header' | 'hmac',
    auth_header_name: '',
    auth_value: '',
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.reset();
            form.parameters_schema = exampleSchema;
            form.webhook_method = 'POST';
            form.auth_type = 'none';
            form.clearErrors();
        }
    },
);

watch(
    () => form.auth_type,
    () => {
        form.auth_header_name = '';
        form.auth_value = '';
        form.clearErrors('auth_header_name', 'auth_value');
    },
);

function submit(): void {
    form.post(route('settings.functions.store'), {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Add a function</DialogTitle>
                <DialogDescription>
                    Functions let your agents call your own APIs as LLM tools. The <code class="text-xs">name</code> and
                    <code class="text-xs">description</code> are what the model sees when deciding whether to call it — be specific.
                    <a
                        href="https://platform.openai.com/docs/guides/function-calling"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-foreground inline-flex items-center gap-1 underline underline-offset-2"
                    >
                        Learn more
                        <ExternalLink class="size-3" />
                    </a>
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-5">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <div class="relative">
                        <Hash class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input id="name" v-model="form.name" placeholder="get_order_status" class="pl-9" required />
                    </div>
                    <p class="text-muted-foreground text-xs">Letters, numbers, underscore. Must start with a letter.</p>
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <div class="relative">
                        <AlignLeft class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            id="description"
                            v-model="form.description"
                            placeholder="Look up the current status of a customer's order by id."
                            class="pl-9"
                            required
                        />
                    </div>
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label>Parameters (JSON schema)</Label>
                    <JsonCodeEditor v-model="form.parameters_schema" :rows="10" aria-label="Function parameters schema" />
                    <InputError :message="form.errors.parameters_schema" />
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2 grid gap-2">
                        <Label for="webhook_url">Webhook URL</Label>
                        <div class="relative">
                            <Webhook class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                id="webhook_url"
                                v-model="form.webhook_url"
                                type="url"
                                placeholder="https://example.com/webhook"
                                class="pl-9"
                                required
                            />
                        </div>
                        <InputError :message="form.errors.webhook_url" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="webhook_method">Method</Label>
                        <div class="relative">
                            <Send class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <select
                                id="webhook_method"
                                v-model="form.webhook_method"
                                class="border-input bg-background h-9 w-full rounded-md border pr-3 pl-9 text-sm"
                            >
                                <option value="POST">POST</option>
                                <option value="GET">GET</option>
                                <option value="PATCH">PATCH</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="auth_type">Auth</Label>
                    <div class="relative">
                        <Shield class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <select
                            id="auth_type"
                            v-model="form.auth_type"
                            class="border-input bg-background h-9 w-full rounded-md border pr-3 pl-9 text-sm"
                        >
                            <option value="none">None — no auth header sent</option>
                            <option value="bearer">Bearer token — sent as `Authorization: Bearer …`</option>
                            <option value="header">Custom header — your own header name + value</option>
                            <option value="hmac">HMAC — body signed with a shared secret</option>
                        </select>
                    </div>

                    <div v-if="form.auth_type === 'bearer'" class="mt-2 grid gap-2">
                        <Label for="auth_value">Bearer token</Label>
                        <div class="relative">
                            <KeyRound class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                id="auth_value"
                                v-model="form.auth_value"
                                type="password"
                                autocomplete="off"
                                placeholder="sk-..."
                                class="pl-9"
                            />
                        </div>
                        <p class="text-muted-foreground text-xs">Sent as <code class="text-xs">Authorization: Bearer &lt;token&gt;</code>.</p>
                        <InputError :message="form.errors.auth_value" />
                    </div>

                    <div v-else-if="form.auth_type === 'header'" class="mt-2 grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="auth_header_name">Header name</Label>
                            <div class="relative">
                                <Key class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    id="auth_header_name"
                                    v-model="form.auth_header_name"
                                    placeholder="X-Api-Key"
                                    class="pl-9"
                                />
                            </div>
                            <InputError :message="form.errors.auth_header_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="auth_value">Header value</Label>
                            <div class="relative">
                                <Lock class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input id="auth_value" v-model="form.auth_value" type="password" autocomplete="off" class="pl-9" />
                            </div>
                            <InputError :message="form.errors.auth_value" />
                        </div>
                    </div>

                    <div v-else-if="form.auth_type === 'hmac'" class="mt-2 grid gap-2">
                        <Label for="auth_value">Signing secret</Label>
                        <div class="relative">
                            <Lock class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input id="auth_value" v-model="form.auth_value" type="password" autocomplete="off" class="pl-9" />
                        </div>
                        <p class="text-muted-foreground text-xs">Used to HMAC-SHA256 the request body; verify on your end.</p>
                        <InputError :message="form.errors.auth_value" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="emit('update:open', false)">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        <Plus v-else class="mr-2 size-4" />
                        Add function
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
