<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { AlertCircle, Check, Copy, KeyRound, Plus, Trash2 } from 'lucide-vue-next';

interface TokenRow {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string | null;
}

interface CreatedToken {
    id: number;
    name: string;
    plaintext: string;
}

interface Props {
    tokens: TokenRow[];
    created_token?: CreatedToken | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'API keys', href: '/settings/api-keys' }];

const form = useForm({ name: '' });

const submit = () => {
    form.post(route('api-keys.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const revokeOpen = ref(false);
const tokenToRevoke = ref<TokenRow | null>(null);

const revoke = (token: TokenRow) => {
    tokenToRevoke.value = token;
    revokeOpen.value = true;
};

const confirmRevoke = () => {
    if (!tokenToRevoke.value) return;
    router.delete(route('api-keys.destroy', { tokenId: tokenToRevoke.value.id }), {
        preserveScroll: true,
    });
    revokeOpen.value = false;
};

// One-time reveal dialog: shows when a fresh token comes back from the server,
// then closing it clears the flash so a page reload won't show it again.
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
    // Refresh server props so created_token clears from flash on next visit.
    router.reload({ only: ['created_token'] });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="API keys" />

        <SettingsLayout width="wide">
            <div class="flex flex-col space-y-8">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <KeyRound class="size-6" />
                        API keys
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Account keys for the <a class="underline" href="/docs/api" target="_blank">Chatvia API</a> — create and manage agents,
                        knowledge, MCP servers, and functions.
                    </p>
                </div>

                <Separator />

                <Card>
                    <CardHeader>
                        <CardTitle>Generate a new key</CardTitle>
                        <CardDescription>
                            Pick a name you'll recognize later — e.g. <code>production-backend</code>. The token itself is shown only once.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="grid flex-1 gap-2">
                                <Label for="name">Name</Label>
                                <Input id="name" v-model="form.name" placeholder="production-backend" autocomplete="off" required />
                                <InputError :message="form.errors.name" />
                            </div>
                            <Button type="submit" :disabled="form.processing">
                                <Plus class="mr-2 size-4" />
                                Generate
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Existing keys</CardTitle>
                        <CardDescription> Revoke any key here. Revoked keys stop working immediately and cannot be restored. </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="tokens.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                            No API keys yet. Generate one above to get started.
                        </div>
                        <ul v-else class="divide-border divide-y">
                            <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ token.name }}</p>
                                    <p class="text-muted-foreground text-xs">
                                        Created {{ token.created_at }} ·
                                        <span v-if="token.last_used_at">Last used {{ token.last_used_at }}</span>
                                        <span v-else>Never used</span>
                                    </p>
                                </div>
                                <Button variant="ghost" size="sm" @click="revoke(token)">
                                    <Trash2 class="size-4 text-red-600" />
                                </Button>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- One-time reveal dialog -->
            <Dialog :open="showCreatedDialog" @update:open="dismissDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <KeyRound class="size-5" />
                            Copy your new API key
                        </DialogTitle>
                        <DialogDescription>
                            This is the only time you'll see <strong>{{ created_token?.name }}</strong
                            >. Store it somewhere safe — if you lose it, generate a new one.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="bg-muted text-foreground flex items-center gap-2 rounded-md border px-3 py-2 font-mono text-xs">
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
                        <span> Treat this like a password. Anyone with it can provision agents on your behalf. </span>
                    </div>

                    <DialogFooter>
                        <Button type="button" @click="dismissDialog">I've stored it safely</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Revoke confirmation -->
            <AlertDialog v-model:open="revokeOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Revoke API key</AlertDialogTitle>
                        <AlertDialogDescription>
                            Revoke <span class="font-medium">{{ tokenToRevoke?.name }}</span
                            >? Any service using this token will stop working immediately. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="confirmRevoke">
                            Revoke
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </SettingsLayout>
    </AppLayout>
</template>
