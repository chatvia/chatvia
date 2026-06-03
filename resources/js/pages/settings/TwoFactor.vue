<script setup lang="ts">
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
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, KeyRound, Loader2, Smartphone } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    enabled: boolean;
    confirmed: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Two-factor authentication', href: '/settings/two-factor' }];

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
    enableForm.post(route('two-factor.enable'), {
        preserveScroll: true,
        onSuccess: () => fetchQr(),
    });
};

const fetchQr = async () => {
    loadingQr.value = true;
    try {
        const [qrRes, secretRes] = await Promise.all([
            fetch(route('two-factor.qr-code'), { headers: { Accept: 'application/json' } }),
            fetch(route('two-factor.secret-key'), { headers: { Accept: 'application/json' } }),
        ]);
        qrSvg.value = (await qrRes.json()).svg;
        secretKey.value = (await secretRes.json()).secretKey;
    } finally {
        loadingQr.value = false;
    }
};

const confirm2fa = async () => {
    if (!(await ensureConfirmed())) return;
    confirmForm.post(route('two-factor.confirm'), {
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
        const res = await fetch(route('two-factor.recovery-codes'), { headers: { Accept: 'application/json' } });
        recoveryCodes.value = await res.json();
    } finally {
        loadingRecovery.value = false;
    }
};

const regenerateCodes = async () => {
    if (!(await ensureConfirmed())) return;
    router.post(
        route('two-factor.regenerate-recovery-codes'),
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
    disableForm.delete(route('two-factor.disable'), {
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
        <Head title="Two-factor authentication" />

        <ConfirmPasswordDialog ref="confirmDlg" />

        <AlertDialog v-model:open="disableDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Disable two-factor authentication?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Your account will only be protected by a password. You can re-enable two-factor at any time.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="confirmDisable2fa">
                        Disable
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <SettingsLayout>
            <div class="space-y-8">
                <div class="flex flex-col space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-primary/10 rounded-full p-2">
                            <Smartphone class="text-primary size-6" />
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight">Two-factor authentication</h1>
                    </div>
                    <p class="text-muted-foreground">Require a 6-digit code from an authenticator app at sign-in, in addition to your password.</p>
                </div>

                <Separator />

                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium">Status</p>
                    <span
                        v-if="props.confirmed"
                        class="inline-flex items-center gap-1 rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700 dark:bg-green-950/50 dark:text-green-300"
                    >
                        <CheckCircle2 class="size-3.5" />
                        Enabled
                    </span>
                    <span v-else class="text-muted-foreground text-xs">Disabled</span>
                </div>

                <!-- Disabled state -->
                <div v-if="!props.enabled">
                    <Button :disabled="enableForm.processing" @click="enable2fa">
                        <Loader2 v-if="enableForm.processing" class="mr-2 size-4 animate-spin" />
                        Enable two-factor authentication
                    </Button>
                </div>

                <!-- Enabled, awaiting confirmation -->
                <div v-else-if="!props.confirmed" class="space-y-4">
                    <Alert>
                        <AlertTitle>Finish setup</AlertTitle>
                        <AlertDescription>
                            Scan the QR code with your authenticator app (1Password, Authy, Google Authenticator, etc.), then enter the 6-digit code
                            it generates to confirm.
                        </AlertDescription>
                    </Alert>

                    <div v-if="loadingQr" class="text-muted-foreground flex items-center gap-2 text-sm">
                        <Loader2 class="size-4 animate-spin" /> Loading QR code…
                    </div>

                    <div v-else-if="qrSvg" class="space-y-4">
                        <div class="inline-block rounded-lg border bg-white p-4" v-html="qrSvg" />

                        <div v-if="secretKey" class="space-y-1">
                            <Label class="text-muted-foreground text-xs uppercase">Or enter this secret manually</Label>
                            <code class="bg-muted block rounded px-3 py-2 font-mono text-sm break-all">{{ secretKey }}</code>
                        </div>
                    </div>

                    <form @submit.prevent="confirm2fa" class="space-y-3">
                        <div class="space-y-2">
                            <Label for="confirm-code">Confirmation code</Label>
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
                                <Loader2 v-if="confirmForm.processing" class="mr-2 size-4 animate-spin" />
                                Confirm
                            </Button>
                            <Button type="button" variant="ghost" :disabled="disableForm.processing" @click="disable2fa"> Cancel setup </Button>
                        </div>
                    </form>
                </div>

                <!-- Confirmed -->
                <div v-else class="space-y-4">
                    <Alert>
                        <KeyRound class="size-4" />
                        <AlertTitle>Recovery codes</AlertTitle>
                        <AlertDescription>
                            Store these somewhere safe. Each can be used once if you lose access to your authenticator.
                        </AlertDescription>
                    </Alert>

                    <div v-if="loadingRecovery" class="text-muted-foreground flex items-center gap-2 text-sm">
                        <Loader2 class="size-4 animate-spin" /> Loading recovery codes…
                    </div>
                    <div v-else-if="recoveryCodes.length" class="bg-muted rounded p-4">
                        <div class="grid grid-cols-2 gap-2 font-mono text-sm">
                            <code v-for="code in recoveryCodes" :key="code">{{ code }}</code>
                        </div>
                    </div>
                    <div v-else>
                        <Button variant="outline" @click="fetchRecoveryCodes">Show recovery codes</Button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" @click="regenerateCodes">Regenerate codes</Button>
                        <Button variant="destructive" :disabled="disableForm.processing" @click="disable2fa">
                            <Loader2 v-if="disableForm.processing" class="mr-2 size-4 animate-spin" />
                            Disable two-factor
                        </Button>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
