<script setup lang="ts">
// The user's passkeys: list, register (browser WebAuthn prompt) and delete.
// Registering and deleting both ask for the password first.
import ConfirmPasswordDialog from '@/components/ConfirmPasswordDialog.vue';
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
import { useDateFormat } from '@/composables/useDateFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import AccountLayout from '@/layouts/account/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Fingerprint, Loader2, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { formatDate, formatDateTime } = useDateFormat();

interface Passkey {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string;
}

const props = defineProps<{
    passkeys: Passkey[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: t('account.passkeys.title'), href: '/my/account/passkeys' }];

const confirmDlg = ref<InstanceType<typeof ConfirmPasswordDialog> | null>(null);
const ensureConfirmed = async (): Promise<boolean> => (await confirmDlg.value?.open()) ?? false;

const registerOpen = ref(false);
const registeringPasskey = ref(false);
const passkeyName = ref('');
const passkeyError = ref<string | null>(null);

const openRegister = () => {
    passkeyName.value = '';
    passkeyError.value = null;
    registerOpen.value = true;
};

const registerPasskey = async () => {
    const name = passkeyName.value.trim();
    if (!name) {
        passkeyError.value = t('account.passkeys.name_required');
        return;
    }

    // Close this dialog before opening the confirm-password dialog — the
    // codebase never stacks two modal dialogs (see TwoFactor.vue).
    passkeyError.value = null;
    registerOpen.value = false;
    if (!(await ensureConfirmed())) return;

    registeringPasskey.value = true;
    try {
        const { Passkeys } = await import('@laravel/passkeys');
        await Passkeys.register({ name });
        passkeyName.value = '';
        router.reload({ only: ['passkeys'] });
    } catch (e: unknown) {
        passkeyError.value = e instanceof Error ? e.message : t('account.passkeys.register_error');
        registerOpen.value = true; // reopen so the error stays visible
    } finally {
        registeringPasskey.value = false;
    }
};

// The pending passkey is held separately from the AlertDialog's open state.
// reka's <AlertDialogAction> fires the dialog close — which clears the open
// state — BEFORE our @click handler runs. If the target lived on the
// open-state ref, confirmDelete would read null and bail out before the
// password prompt, so the dialog would just vanish and nothing would happen.
const deleteDialogOpen = ref(false);
const passkeyToDelete = ref<Passkey | null>(null);

const deletePasskey = (passkey: Passkey) => {
    passkeyToDelete.value = passkey;
    deleteDialogOpen.value = true;
};

const confirmDelete = async () => {
    const target = passkeyToDelete.value;
    deleteDialogOpen.value = false;
    if (!target) return;
    if (!(await ensureConfirmed())) return;
    router.delete(route('passkey.destroy', { passkey: target.id }), {
        preserveScroll: true,
        only: ['passkeys'],
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('account.passkeys.title')" />

        <ConfirmPasswordDialog ref="confirmDlg" />

        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('account.passkeys.delete_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('account.passkeys.delete_description', { name: passkeyToDelete?.name ?? '' }) }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('common.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="confirmDelete">
                        {{ t('common.remove') }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <AccountLayout width="wide">
            <div class="w-full space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Fingerprint class="size-6" />
                            {{ t('account.passkeys.heading') }}
                        </h1>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ t('account.passkeys.description') }}
                        </p>
                    </div>
                    <Button @click="openRegister">
                        <Plus class="me-1 size-4" />
                        {{ t('account.passkeys.add_passkey') }}
                    </Button>
                </div>

                <Separator />

                <!-- List -->
                <div v-if="props.passkeys.length === 0" class="rounded-lg border py-16 text-center">
                    <Fingerprint class="text-muted-foreground mx-auto size-8" />
                    <p class="mt-3 text-sm font-medium">{{ t('account.passkeys.no_passkeys') }}</p>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('account.passkeys.no_passkeys_hint') }}</p>
                    <Button class="mt-4" @click="openRegister">
                        <Plus class="me-1 size-4" />
                        {{ t('account.passkeys.add_passkey') }}
                    </Button>
                </div>

                <div v-else class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="h-11 ps-5">{{ t('account.profile.name') }}</TableHead>
                                <TableHead class="h-11 w-48">{{ t('account.passkeys.last_used') }}</TableHead>
                                <TableHead class="h-11 w-40">{{ t('account.passkeys.created') }}</TableHead>
                                <TableHead class="h-11 w-16 pe-5 text-end">{{ t('common.remove') }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="passkey in props.passkeys" :key="passkey.id">
                                <TableCell class="py-4 ps-5">
                                    <div class="flex items-center gap-4">
                                        <div class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-md">
                                            <Fingerprint class="text-muted-foreground size-5" />
                                        </div>
                                        <p class="leading-tight font-medium">{{ passkey.name }}</p>
                                    </div>
                                </TableCell>
                                <TableCell class="text-muted-foreground py-4 text-sm">
                                    {{ passkey.last_used_at ? formatDateTime(passkey.last_used_at) : t('account.passkeys.never') }}
                                </TableCell>
                                <TableCell class="text-muted-foreground py-4 text-sm">
                                    {{ formatDate(passkey.created_at) }}
                                </TableCell>
                                <TableCell class="py-4 pe-5 text-end">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="deletePasskey(passkey)"
                                        :aria-label="t('account.passkeys.delete_aria')"
                                    >
                                        <Trash2 class="size-4 text-red-600" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Register a passkey -->
            <Dialog v-model:open="registerOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Fingerprint class="size-5" />
                            {{ t('account.passkeys.dialog_title') }}
                        </DialogTitle>
                        <DialogDescription>{{ t('account.passkeys.dialog_description') }}</DialogDescription>
                    </DialogHeader>

                    <form id="register-passkey-form" @submit.prevent="registerPasskey" class="grid gap-2">
                        <Label for="passkey-name">{{ t('account.profile.name') }}</Label>
                        <Input id="passkey-name" v-model="passkeyName" :placeholder="t('account.passkeys.name_placeholder')" autocomplete="off" />
                        <p v-if="passkeyError" class="text-destructive text-sm">{{ passkeyError }}</p>
                    </form>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="registerOpen = false">{{ t('common.cancel') }}</Button>
                        <Button type="submit" form="register-passkey-form" :disabled="registeringPasskey">
                            <Loader2 v-if="registeringPasskey" class="me-1 size-4 animate-spin" />
                            <Plus v-else class="me-1 size-4" />
                            {{ t('account.passkeys.register') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AccountLayout>
    </AppLayout>
</template>
