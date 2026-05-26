<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { AlertCircle, Check, Copy, KeyRound, Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface TokenRow {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    last_used_iso: string | null;
    expires_at: string | null;
    created_at: string | null;
}

interface CreatedToken {
    id: number;
    name: string;
    plaintext: string;
    abilities: string[];
}

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
    tokens: TokenRow[];
    available_abilities: string[];
    created_token?: CreatedToken | null;
}

const props = defineProps<Props>();

const allAbilities = props.available_abilities;

const form = useForm<{
    name: string;
    abilities: string[];
    expires_in_days: number | null;
}>({
    name: '',
    abilities: [...allAbilities],
    expires_in_days: null,
});

const submit = () => {
    form.post(route('agents.api-tokens.store', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.abilities = [...allAbilities];
        },
    });
};

const revoke = (token: TokenRow) => {
    if (!confirm(`Revoke "${token.name}"? Any service using this token will stop working immediately.`)) {
        return;
    }

    router.delete(route('agents.api-tokens.destroy', { agent: props.agent.external_id, tokenId: token.id }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Token revoked'),
    });
};

const showCreatedDialog = ref(false);
const copied = ref(false);

watch(
    () => props.created_token,
    (value) => {
        if (value) showCreatedDialog.value = true;
    },
    { immediate: true },
);

const copyPlaintext = async () => {
    if (!props.created_token) return;
    await navigator.clipboard.writeText(props.created_token.plaintext);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const dismissDialog = () => {
    showCreatedDialog.value = false;
    router.reload({ only: ['created_token'] });
};

const toggleAbility = (ability: string, checked: boolean) => {
    if (checked) {
        if (!form.abilities.includes(ability)) form.abilities.push(ability);
    } else {
        form.abilities = form.abilities.filter((a) => a !== ability);
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="API tokens" description="Bearer tokens that authenticate calls to this agent via the public API.">
        <template #header-icon>
            <KeyRound class="size-5" />
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Generate a new token</CardTitle>
                    <CardDescription>
                        The plaintext is shown only once after creation. Pick the abilities you need — least privilege is safer than the default of
                        all.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-5">
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" placeholder="e.g. acme-prod-backend" autocomplete="off" required />
                            <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid gap-2">
                            <Label>Abilities</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    v-for="ability in allAbilities"
                                    :key="ability"
                                    class="border-input flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                                >
                                    <Checkbox
                                        :model-value="form.abilities.includes(ability)"
                                        @update:model-value="(v) => toggleAbility(ability, Boolean(v))"
                                    />
                                    <code class="text-xs">{{ ability }}</code>
                                </label>
                            </div>
                            <p v-if="form.errors.abilities" class="text-xs text-red-500">{{ form.errors.abilities }}</p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="expires">Expires in (days)</Label>
                            <Input
                                id="expires"
                                v-model.number="form.expires_in_days"
                                type="number"
                                min="1"
                                max="730"
                                placeholder="Leave blank for no expiry"
                            />
                            <p v-if="form.errors.expires_in_days" class="text-xs text-red-500">
                                {{ form.errors.expires_in_days }}
                            </p>
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing || form.abilities.length === 0">
                                <Plus class="mr-2 size-4" />
                                Generate token
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Existing tokens</CardTitle>
                    <CardDescription>
                        Last-used time is updated each time the token authenticates a request. Revoke unused or leaked tokens immediately.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="tokens.length === 0" class="text-muted-foreground py-6 text-center text-sm">No tokens yet. Generate one above.</div>
                    <ul v-else class="divide-border divide-y">
                        <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ token.name }}</p>
                                <p class="text-muted-foreground mt-0.5 text-xs">
                                    Created {{ token.created_at }} ·
                                    <span v-if="token.last_used_at" :title="token.last_used_iso ?? ''"> Last used {{ token.last_used_at }} </span>
                                    <span v-else>Never used</span>
                                    <span v-if="token.expires_at"> · Expires {{ token.expires_at }}</span>
                                </p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <code
                                        v-for="ability in token.abilities"
                                        :key="ability"
                                        class="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-[10px]"
                                    >
                                        {{ ability }}
                                    </code>
                                </div>
                            </div>
                            <Button variant="ghost" size="sm" @click="revoke(token)">
                                <Trash2 class="size-4 text-red-600" />
                            </Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <Dialog :open="showCreatedDialog" @update:open="dismissDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <KeyRound class="size-5" />
                        Copy your new token
                    </DialogTitle>
                    <DialogDescription>
                        This is the only time you'll see the plaintext of
                        <strong>{{ created_token?.name }}</strong
                        >. If you lose it, generate a new one.
                    </DialogDescription>
                </DialogHeader>

                <div class="bg-muted flex items-center gap-2 rounded-md border px-3 py-2 font-mono text-xs">
                    <span class="flex-1 break-all">{{ created_token?.plaintext }}</span>
                    <Button type="button" variant="outline" size="sm" @click="copyPlaintext">
                        <Check v-if="copied" class="size-4 text-green-600" />
                        <Copy v-else class="size-4" />
                    </Button>
                </div>

                <div
                    class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <AlertCircle class="mt-0.5 size-4 flex-shrink-0" />
                    <span>Treat this like a password. Anyone with it can act as this agent through the API.</span>
                </div>

                <DialogFooter>
                    <Button type="button" @click="dismissDialog">I've stored it safely</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
