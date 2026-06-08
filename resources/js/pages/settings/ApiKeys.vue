<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import LockedFeature from '@/components/LockedFeature.vue';
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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useCapabilityGuard } from '@/composables/useCapabilityGuard';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { AlertCircle, Check, Copy, KeyRound, Plus, Tag, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const { hasCapability } = useCapabilityGuard();
const hasAccess = computed(() => hasCapability('api'));

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

const generateOpen = ref(false);

const openGenerate = () => {
    form.reset();
    form.clearErrors();
    generateOpen.value = true;
};

const submit = () => {
    form.post(route('api-keys.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            generateOpen.value = false;
        },
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
            <div class="w-full space-y-6">
                <div class="flex items-start justify-between gap-4">
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
                    <Button v-if="hasAccess" @click="openGenerate">
                        <Plus class="mr-1 size-4" />
                        Generate key
                    </Button>
                </div>

                <Separator />

                <LockedFeature v-if="!hasAccess" description="Upgrade your plan to issue API keys and integrate Chatvia with your applications." />

                <template v-else>
                    <div v-if="tokens.length === 0" class="rounded-lg border py-16 text-center">
                        <KeyRound class="text-muted-foreground mx-auto size-8" />
                        <p class="mt-3 text-sm font-medium">No API keys yet</p>
                        <p class="text-muted-foreground mt-1 text-sm">Generate one to start using the Chatvia API.</p>
                        <Button class="mt-4" @click="openGenerate">
                            <Plus class="mr-1 size-4" />
                            Generate key
                        </Button>
                    </div>

                    <div v-else class="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="h-11 pl-5">Name</TableHead>
                                    <TableHead class="h-11 w-40">Created</TableHead>
                                    <TableHead class="h-11 w-40">Last used</TableHead>
                                    <TableHead class="h-11 w-16 pr-5 text-right">Revoke</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="token in tokens" :key="token.id">
                                    <TableCell class="py-4 pl-5">
                                        <div class="flex items-center gap-4">
                                            <div class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-md">
                                                <KeyRound class="text-muted-foreground size-5" />
                                            </div>
                                            <p class="leading-tight font-medium">{{ token.name }}</p>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground py-4 text-sm">{{ token.created_at ?? '—' }}</TableCell>
                                    <TableCell class="text-muted-foreground py-4 text-sm">
                                        {{ token.last_used_at ?? 'Never' }}
                                    </TableCell>
                                    <TableCell class="py-4 pr-5 text-right">
                                        <Button variant="ghost" size="icon" aria-label="Revoke key" @click="revoke(token)">
                                            <Trash2 class="size-4 text-red-600" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </template>
            </div>

            <!-- Generate a new key -->
            <Dialog v-model:open="generateOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Plus class="size-5" />
                            Generate a new key
                        </DialogTitle>
                        <DialogDescription>
                            Pick a name you'll recognize later — e.g. <code>production-backend</code>. The token itself is shown only once.
                        </DialogDescription>
                    </DialogHeader>

                    <form id="generate-key-form" @submit.prevent="submit" class="grid gap-2">
                        <Label for="name">Name</Label>
                        <div class="relative">
                            <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Tag class="size-4" />
                            </div>
                            <Input id="name" v-model="form.name" placeholder="production-backend" autocomplete="off" required class="pl-9" />
                        </div>
                        <InputError :message="form.errors.name" />
                    </form>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="generateOpen = false">Cancel</Button>
                        <Button type="submit" form="generate-key-form" :disabled="form.processing">
                            <Plus class="mr-1 size-4" />
                            Generate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

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
                        <Button type="button" @click="dismissDialog">
                            <Check class="mr-1 size-4" />
                            I've stored it safely
                        </Button>
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
