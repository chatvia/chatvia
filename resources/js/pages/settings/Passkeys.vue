<script setup lang="ts">
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Fingerprint, Loader2, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Passkey {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string;
}

const props = defineProps<{
    passkeys: Passkey[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Passkeys', href: '/settings/passkeys' }];

const confirmDlg = ref<InstanceType<typeof ConfirmPasswordDialog> | null>(null);
const ensureConfirmed = async (): Promise<boolean> => (await confirmDlg.value?.open()) ?? false;

const registeringPasskey = ref(false);
const passkeyName = ref('');
const passkeyError = ref<string | null>(null);

const registerPasskey = async () => {
    if (!passkeyName.value.trim()) {
        passkeyError.value = 'Give your passkey a name first.';
        return;
    }
    if (!(await ensureConfirmed())) return;

    passkeyError.value = null;
    registeringPasskey.value = true;
    try {
        const { Passkeys } = await import('@laravel/passkeys');
        await Passkeys.register({ name: passkeyName.value.trim() });
        passkeyName.value = '';
        router.reload({ only: ['passkeys'] });
    } catch (e: unknown) {
        passkeyError.value = e instanceof Error ? e.message : 'Could not register passkey.';
    } finally {
        registeringPasskey.value = false;
    }
};

const passkeyToDelete = ref<Passkey | null>(null);

const deletePasskey = (passkey: Passkey) => {
    passkeyToDelete.value = passkey;
};

const confirmDelete = async () => {
    const target = passkeyToDelete.value;
    passkeyToDelete.value = null;
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
        <Head title="Passkeys" />

        <ConfirmPasswordDialog ref="confirmDlg" />

        <AlertDialog :open="passkeyToDelete !== null" @update:open="(v) => !v && (passkeyToDelete = null)">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete this passkey?</AlertDialogTitle>
                    <AlertDialogDescription>
                        <span class="font-medium">{{ passkeyToDelete?.name }}</span> will no longer be able to sign in. This can't be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="confirmDelete">
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <SettingsLayout width="wide">
            <div class="space-y-8">
                <div class="flex flex-col space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-primary/10 rounded-full p-2">
                            <Fingerprint class="text-primary size-6" />
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight">Passkeys</h1>
                    </div>
                    <p class="text-muted-foreground">
                        Sign in with Face ID, Touch ID, Windows Hello, or a hardware security key — no password needed.
                    </p>
                </div>

                <Separator />

                <!-- Register -->
                <form @submit.prevent="registerPasskey" class="space-y-3">
                    <div class="space-y-2">
                        <Label for="passkey-name">Name this passkey</Label>
                        <Input id="passkey-name" v-model="passkeyName" placeholder="MacBook Pro, iPhone, YubiKey…" class="max-w-md" />
                        <p v-if="passkeyError" class="text-destructive text-sm">{{ passkeyError }}</p>
                    </div>
                    <Button type="submit" :disabled="registeringPasskey">
                        <Loader2 v-if="registeringPasskey" class="mr-2 size-4 animate-spin" />
                        <Plus v-else class="mr-2 size-4" />
                        Register a passkey
                    </Button>
                </form>

                <Separator v-if="props.passkeys.length" />

                <!-- List -->
                <div v-if="props.passkeys.length" class="space-y-3">
                    <div v-for="passkey in props.passkeys" :key="passkey.id" class="flex items-center justify-between gap-4 rounded-lg border p-4">
                        <div class="flex items-center gap-3">
                            <Fingerprint class="text-muted-foreground size-5" />
                            <div>
                                <p class="font-medium">{{ passkey.name }}</p>
                                <p class="text-muted-foreground text-xs">
                                    <span v-if="passkey.last_used_at">Last used {{ new Date(passkey.last_used_at).toLocaleString() }}</span>
                                    <span v-else>Never used</span>
                                    · Added {{ new Date(passkey.created_at).toLocaleDateString() }}
                                </p>
                            </div>
                        </div>
                        <Button variant="ghost" size="icon" @click="deletePasskey(passkey)" aria-label="Delete passkey">
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
                <p v-else class="text-muted-foreground text-sm">No passkeys registered yet.</p>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
