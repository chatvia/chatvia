<script setup lang="ts">
/**
 * Workspace → Integrations → API keys: generate and revoke bearer tokens for
 * the public API. A new key's plaintext is shown once, in a reveal dialog,
 * straight after the server creates it.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { apiDocsUrl } from '@/lib/apiDocs';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkspaceLayout from '@/layouts/workspace/Layout.vue';
import { destroy as destroyApiKey, store as storeApiKey } from '@/routes/integrations/api-keys';
import { type BreadcrumbItem } from '@/types';
import { AlertCircle, Check, Copy, KeyRound, Plus, Tag, Trash2 } from 'lucide-vue-next';

const { t } = useI18n();

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

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.api_keys.breadcrumb'), href: '/my/workspace/integrations/api-keys' }];

const form = useForm({ name: '' });

const generateOpen = ref(false);

const openGenerate = () => {
    form.reset();
    form.clearErrors();
    generateOpen.value = true;
};

const submit = () => {
    form.post(storeApiKey.url(), {
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
    router.delete(destroyApiKey.url(tokenToRevoke.value.id), {
        preserveScroll: true,
    });
    revokeOpen.value = false;
};

// One-time reveal dialog: shows when a fresh token comes back from the server,
// then closing it clears the flash so a page reload won't show it again.
const showCreatedDialog = ref(false);
const { copy, copied } = useClipboard({ legacy: true, copiedDuring: 2000 });

watch(
    () => props.created_token,
    (value) => {
        if (value) showCreatedDialog.value = true;
    },
    { immediate: true },
);

const copyPlaintext = async () => {
    if (!props.created_token) return;
    await copy(props.created_token.plaintext);
};

const dismissDialog = () => {
    showCreatedDialog.value = false;
    // Refresh server props so created_token clears from flash on next visit.
    router.reload({ only: ['created_token'] });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('workspace.api_keys.title')" />

        <WorkspaceLayout width="wide">
            <div class="w-full space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <KeyRound class="size-6" />
                            {{ t('workspace.api_keys.title') }}
                        </h1>
                        <p class="text-muted-foreground mt-1 text-sm">
                            <i18n-t keypath="workspace.api_keys.description" tag="span">
                                <template #api_link
                                    ><a class="underline" :href="apiDocsUrl()" target="_blank">{{ t('workspace.api_keys.api_link') }}</a></template
                                >
                            </i18n-t>
                        </p>
                    </div>
                    <Button @click="openGenerate">
                        <Plus class="me-1 size-4" />
                        {{ t('workspace.api_keys.generate') }}
                    </Button>
                </div>

                <Separator />

                <div v-if="tokens.length === 0" class="rounded-lg border py-16 text-center">
                    <KeyRound class="text-muted-foreground mx-auto size-8" />
                    <p class="mt-3 text-sm font-medium">{{ t('workspace.api_keys.empty_title') }}</p>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('workspace.api_keys.empty_description') }}</p>
                    <Button class="mt-4" @click="openGenerate">
                        <Plus class="me-1 size-4" />
                        {{ t('workspace.api_keys.generate') }}
                    </Button>
                </div>

                <div v-else class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="h-11 ps-5">{{ t('workspace.api_keys.table.name') }}</TableHead>
                                <TableHead class="h-11 w-40">{{ t('workspace.api_keys.table.created') }}</TableHead>
                                <TableHead class="h-11 w-40">{{ t('workspace.api_keys.table.last_used') }}</TableHead>
                                <TableHead class="h-11 w-16 pe-5 text-end">{{ t('workspace.api_keys.table.revoke') }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="token in tokens" :key="token.id">
                                <TableCell class="py-4 ps-5">
                                    <div class="flex items-center gap-4">
                                        <div class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-md">
                                            <KeyRound class="text-muted-foreground size-5" />
                                        </div>
                                        <p class="leading-tight font-medium">{{ token.name }}</p>
                                    </div>
                                </TableCell>
                                <TableCell class="text-muted-foreground py-4 text-sm">{{ token.created_at ?? '—' }}</TableCell>
                                <TableCell class="text-muted-foreground py-4 text-sm">
                                    {{ token.last_used_at ?? t('workspace.api_keys.never') }}
                                </TableCell>
                                <TableCell class="py-4 pe-5 text-end">
                                    <Button variant="ghost" size="icon" :aria-label="t('workspace.api_keys.revoke_aria')" @click="revoke(token)">
                                        <Trash2 class="size-4 text-red-600" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Generate a new key -->
            <Dialog v-model:open="generateOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Plus class="size-5" />
                            {{ t('workspace.api_keys.generate_dialog.title') }}
                        </DialogTitle>
                        <DialogDescription>
                            <i18n-t keypath="workspace.api_keys.generate_dialog.description" tag="span">
                                <template #example><code>production-backend</code></template>
                            </i18n-t>
                        </DialogDescription>
                    </DialogHeader>

                    <form id="generate-key-form" @submit.prevent="submit" class="grid gap-2">
                        <Label for="name">{{ t('workspace.api_keys.generate_dialog.name') }}</Label>
                        <div class="relative">
                            <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                <Tag class="size-4" />
                            </div>
                            <Input id="name" v-model="form.name" placeholder="production-backend" autocomplete="off" required class="ps-9" />
                        </div>
                        <InputError :message="form.errors.name" />
                    </form>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="generateOpen = false">{{ t('workspace.common.cancel') }}</Button>
                        <Button type="submit" form="generate-key-form" :disabled="form.processing">
                            <Plus class="me-1 size-4" />
                            {{ t('workspace.api_keys.generate_dialog.submit') }}
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
                            {{ t('workspace.api_keys.reveal_dialog.title') }}
                        </DialogTitle>
                        <DialogDescription>
                            <i18n-t keypath="workspace.api_keys.reveal_dialog.description" tag="span">
                                <template #name
                                    ><strong>{{ created_token?.name }}</strong></template
                                >
                            </i18n-t>
                        </DialogDescription>
                    </DialogHeader>

                    <div class="bg-muted text-foreground flex items-center gap-2 rounded-md border px-3 py-2 font-mono text-xs">
                        <span class="flex-1 break-all">{{ created_token?.plaintext }}</span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :aria-label="t('workspace.api_keys.reveal_dialog.copy')"
                            @click="copyPlaintext"
                        >
                            <Check v-if="copied" class="size-4 text-green-600" />
                            <Copy v-else class="size-4" />
                        </Button>
                    </div>

                    <div
                        class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                    >
                        <AlertCircle class="mt-0.5 size-4 flex-shrink-0" />
                        <span>{{ t('workspace.api_keys.reveal_dialog.warning') }}</span>
                    </div>

                    <DialogFooter>
                        <Button type="button" @click="dismissDialog">
                            <Check class="me-1 size-4" />
                            {{ t('workspace.api_keys.reveal_dialog.confirm') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Revoke confirmation -->
            <AlertDialog v-model:open="revokeOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ t('workspace.api_keys.revoke_dialog.title') }}</AlertDialogTitle>
                        <AlertDialogDescription>
                            <i18n-t keypath="workspace.api_keys.revoke_dialog.description" tag="span">
                                <template #name
                                    ><span class="font-medium">{{ tokenToRevoke?.name }}</span></template
                                >
                            </i18n-t>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{{ t('workspace.common.cancel') }}</AlertDialogCancel>
                        <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="confirmRevoke">
                            {{ t('workspace.api_keys.revoke_dialog.confirm') }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </WorkspaceLayout>
    </AppLayout>
</template>
