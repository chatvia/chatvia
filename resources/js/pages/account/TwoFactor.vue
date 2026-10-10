<script setup lang="ts">
// Authenticator-app two-factor settings. Three states: off (offer to enable),
// enabled but unconfirmed (show the QR code and ask for a code), and confirmed
// (show recovery codes). Every change asks for the password first.
import { getTwoFactorQrCode, getTwoFactorRecoveryCodes, getTwoFactorSecretKey } from '@/api/account';
import ConfirmPasswordDialog from '@/components/ConfirmPasswordDialog.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import AccountLayout from '@/layouts/account/Layout.vue';
import twoFactor from '@/routes/two-factor';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, KeyRound, Loader2, ShieldCheck, Smartphone } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    enabled: boolean;
    confirmed: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: t('account.two_factor.title'), href: '/my/account/two-factor' }];

const confirmDlg = ref<InstanceType<typeof ConfirmPasswordDialog> | null>(null);
const ensureConfirmed = async (): Promise<boolean> => (await confirmDlg.value?.open()) ?? false;

const qrSvg = ref<string | null>(null);
const secretKey = ref<string | null>(null);
const recoveryCodes = ref<string[]>([]);
const loadingQr = ref(false);
const loadingRecovery = ref(false);

const confirmForm = useForm({ code: '' });
const disableForm = useForm({});
const enableForm = useForm({});

const enable2fa = async () => {
    if (!(await ensureConfirmed())) return;
    enableForm.post(twoFactor.enable.url(), {
        preserveScroll: true,
        onSuccess: () => fetchQr(),
    });
};

const fetchQr = async () => {
    loadingQr.value = true;
    try {
        const [qrCode, secret] = await Promise.all([getTwoFactorQrCode(), getTwoFactorSecretKey()]);
        qrSvg.value = qrCode.svg ?? null;
        secretKey.value = secret.secretKey ?? null;
    } finally {
        loadingQr.value = false;
    }
};

const confirm2fa = async () => {
    if (!(await ensureConfirmed())) return;
    confirmForm.post(twoFactor.confirm.url(), {
        preserveScroll: true,
        onSuccess: () => {
            confirmForm.reset();
            qrSvg.value = null;
            secretKey.value = null;
            fetchRecoveryCodes();
        },
    });
};

const fetchRecoveryCodes = async () => {
    loadingRecovery.value = true;
    try {
        recoveryCodes.value = await getTwoFactorRecoveryCodes();
    } finally {
        loadingRecovery.value = false;
    }
};

const regenerateCodes = async () => {
    if (!(await ensureConfirmed())) return;
    router.post(
        twoFactor.regenerateRecoveryCodes.url(),
        {},
        {
            preserveScroll: true,
            onSuccess: () => fetchRecoveryCodes(),
        },
    );
};

const disableDialogOpen = ref(false);

const disable2fa = () => {
    disableDialogOpen.value = true;
};

const confirmDisable2fa = async () => {
    disableDialogOpen.value = false;
    if (!(await ensureConfirmed())) return;
    disableForm.delete(twoFactor.disable.url(), {
        preserveScroll: true,
        onSuccess: () => {
            qrSvg.value = null;
            secretKey.value = null;
            recoveryCodes.value = [];
        },
    });
};

if (props.enabled && !props.confirmed) {
    fetchQr();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('account.two_factor.title')" />

        <ConfirmPasswordDialog ref="confirmDlg" />

        <AlertDialog v-model:open="disableDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('account.two_factor.disable_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('account.two_factor.disable_description') }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('common.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="confirmDisable2fa">
                        {{ t('account.two_factor.disable') }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <AccountLayout width="wide">
            <div class="space-y-8">
                <div class="flex flex-col space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="bg-primary/10 rounded-full p-2">
                            <Smartphone class="text-primary size-6" />
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight">{{ t('account.two_factor.heading') }}</h1>
                    </div>
                    <p class="text-muted-foreground">{{ t('account.two_factor.description') }}</p>
                </div>

                <Separator />

                <div v-if="props.enabled" class="flex items-center justify-between">
                    <p class="text-sm font-medium">{{ t('account.two_factor.status') }}</p>
                    <span
                        v-if="props.confirmed"
                        class="inline-flex items-center gap-1 rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700 dark:bg-green-950/50 dark:text-green-300"
                    >
                        <CheckCircle2 class="size-3.5" />
                        {{ t('account.two_factor.enabled') }}
                    </span>
                    <span v-else class="text-muted-foreground text-xs">{{ t('account.two_factor.disabled') }}</span>
                </div>

                <!-- Disabled state — empty-state card mirroring the passkeys page -->
                <div v-if="!props.enabled" class="rounded-lg border py-16 text-center">
                    <Smartphone class="text-muted-foreground mx-auto size-8" />
                    <p class="mt-3 text-sm font-medium">{{ t('account.two_factor.off_title') }}</p>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('account.two_factor.off_description') }}</p>
                    <Button class="mt-4" :disabled="enableForm.processing" @click="enable2fa">
                        <Loader2 v-if="enableForm.processing" class="me-2 size-4 animate-spin" />
                        <ShieldCheck v-else class="me-1 size-4" />
                        {{ t('account.two_factor.enable') }}
                    </Button>
                </div>

                <!-- Enabled, awaiting confirmation -->
                <div v-else-if="!props.confirmed" class="space-y-4">
                    <Alert>
                        <AlertTitle>{{ t('account.two_factor.finish_setup_title') }}</AlertTitle>
                        <AlertDescription>
                            {{ t('account.two_factor.finish_setup_description') }}
                        </AlertDescription>
                    </Alert>

                    <div v-if="loadingQr" class="text-muted-foreground flex items-center gap-2 text-sm">
                        <Loader2 class="size-4 animate-spin" /> {{ t('account.two_factor.loading_qr') }}
                    </div>

                    <div v-else-if="qrSvg" class="space-y-4">
                        <div class="inline-block rounded-lg border bg-white p-4" v-html="qrSvg" />

                        <div v-if="secretKey" class="space-y-1">
                            <Label class="text-muted-foreground text-xs uppercase">{{ t('account.two_factor.secret_manual') }}</Label>
                            <code class="bg-muted block rounded px-3 py-2 font-mono text-sm break-all">{{ secretKey }}</code>
                        </div>
                    </div>

                    <form @submit.prevent="confirm2fa" class="space-y-3">
                        <div class="space-y-2">
                            <Label for="confirm-code">{{ t('account.two_factor.confirmation_code') }}</Label>
                            <Input
                                id="confirm-code"
                                v-model="confirmForm.code"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                placeholder="123456"
                                class="max-w-xs tracking-widest"
                                required
                            />
                            <InputError :message="confirmForm.errors.code" />
                        </div>
                        <div class="flex gap-2">
                            <Button type="submit" :disabled="confirmForm.processing">
                                <Loader2 v-if="confirmForm.processing" class="me-2 size-4 animate-spin" />
                                {{ t('account.two_factor.confirm') }}
                            </Button>
                            <Button type="button" variant="ghost" :disabled="disableForm.processing" @click="disable2fa">
                                {{ t('account.two_factor.cancel_setup') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Confirmed -->
                <div v-else class="space-y-4">
                    <Alert>
                        <KeyRound class="size-4" />
                        <AlertTitle>{{ t('account.two_factor.recovery_title') }}</AlertTitle>
                        <AlertDescription>
                            {{ t('account.two_factor.recovery_description') }}
                        </AlertDescription>
                    </Alert>

                    <div v-if="loadingRecovery" class="text-muted-foreground flex items-center gap-2 text-sm">
                        <Loader2 class="size-4 animate-spin" /> {{ t('account.two_factor.loading_codes') }}
                    </div>
                    <div v-else-if="recoveryCodes.length" class="bg-muted rounded p-4">
                        <div class="grid grid-cols-2 gap-2 font-mono text-sm">
                            <code v-for="code in recoveryCodes" :key="code">{{ code }}</code>
                        </div>
                    </div>
                    <div v-else>
                        <Button variant="outline" @click="fetchRecoveryCodes">{{ t('account.two_factor.show_codes') }}</Button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" @click="regenerateCodes">{{ t('account.two_factor.regenerate_codes') }}</Button>
                        <Button variant="destructive" :disabled="disableForm.processing" @click="disable2fa">
                            <Loader2 v-if="disableForm.processing" class="me-2 size-4 animate-spin" />
                            {{ t('account.two_factor.disable') }}
                        </Button>
                    </div>
                </div>
            </div>
        </AccountLayout>
    </AppLayout>
</template>
