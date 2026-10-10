<script setup lang="ts">
// Two-step account deletion. Step 1 asks for the password and emails a
// confirmation link; once the link is followed the page loads with
// `confirmed` and step 2 offers the final, irreversible delete.
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

// Components
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
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { destroy as destroyAccount } from '@/routes/account';
import { request as requestDeletion } from '@/routes/account/delete';
import { AlertTriangle, Bot, Download, Loader2, Lock, MailCheck, ShieldAlert, Trash2 } from 'lucide-vue-next';

interface Props {
    agents: { name: string }[];
    confirmed: boolean;
}

const props = defineProps<Props>();

const dialogOpen = ref(false);
const linkSent = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);

const passwordForm = useForm({
    password: '',
});

const destroyForm = useForm({});

const sendConfirmationLink = (e: Event) => {
    e.preventDefault();

    passwordForm.post(requestDeletion.url(), {
        preserveScroll: true,
        onSuccess: () => {
            linkSent.value = true;
            dialogOpen.value = false;
            passwordForm.reset();
        },
        onError: () => passwordInput.value?.focus(),
    });
};

const openDialog = () => {
    passwordForm.clearErrors();
    passwordForm.reset();
    dialogOpen.value = true;
};

const destroy = () => {
    destroyForm.delete(destroyAccount.url(), {
        preserveScroll: true,
        onSuccess: () => {
            // Account and session are gone — do a full-page native redirect to the
            // public home page so no stale Inertia/auth state lingers. (The server
            // returns 204 rather than redirecting, since '/' is a non-Inertia page.)
            window.location.href = '/';
        },
    });
};
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                <Trash2 class="size-6" />
                {{ t('account.delete.title') }}
            </h1>
            <p class="text-muted-foreground mt-1 text-sm">{{ t('account.delete.subtitle') }}</p>
        </div>

        <Separator />

        <!-- Reminder: download a copy of your data first -->
        <div class="flex items-start gap-3 rounded-lg border p-4">
            <Download class="text-muted-foreground mt-0.5 size-5 shrink-0" />
            <div class="space-y-1">
                <p class="text-sm font-medium">{{ t('account.delete.data_export_title') }}</p>
                <p class="text-muted-foreground text-sm">
                    <i18n-t keypath="account.delete.data_export_description" tag="span">
                        <template #link>
                            <Link href="/my/account/data-export" class="text-foreground font-medium underline underline-offset-4">
                                {{ t('account.delete.data_export_link') }}
                            </Link>
                        </template>
                    </i18n-t>
                </p>
            </div>
        </div>

        <!-- Danger zone -->
        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
            <div class="flex items-start gap-3 text-red-600 dark:text-red-100">
                <AlertTriangle class="mt-0.5 size-5 shrink-0" />
                <div class="space-y-1">
                    <p class="font-medium">{{ t('account.delete.cannot_undo') }}</p>
                    <p class="text-sm">{{ t('account.delete.removes_intro') }}</p>
                </div>
            </div>

            <ul class="ms-8 list-disc space-y-1 text-sm text-red-600 dark:text-red-100">
                <li>{{ t('account.delete.removes_agents') }}</li>
                <li>{{ t('account.delete.removes_conversations') }}</li>
                <li>{{ t('account.delete.removes_knowledge') }}</li>
                <li>{{ t('account.delete.removes_team') }}</li>
            </ul>

            <!-- Agents that will be deleted -->
            <div v-if="props.agents.length" class="ms-8 space-y-2">
                <p class="flex items-center gap-2 text-sm font-medium text-red-600 dark:text-red-100">
                    <Bot class="size-4 shrink-0" />
                    {{ t('account.delete.agents_will_delete', { count: props.agents.length }, props.agents.length) }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-for="agent in props.agents"
                        :key="agent.name"
                        class="inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-100"
                    >
                        {{ agent.name }}
                    </span>
                </div>
            </div>

            <!-- State B: email confirmed → final, explicit delete -->
            <template v-if="props.confirmed">
                <div
                    class="flex items-start gap-2 rounded-md border border-red-200 bg-white/60 p-3 text-sm text-red-700 dark:border-red-200/20 dark:bg-transparent dark:text-red-100"
                >
                    <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                    <span>{{ t('account.delete.email_confirmed') }}</span>
                </div>

                <AlertDialog>
                    <AlertDialogTrigger as-child>
                        <Button variant="destructive">
                            <Trash2 class="me-2 size-4" />
                            {{ t('account.delete.delete_button') }}
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{ t('account.delete.final_dialog_title') }}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {{ t('account.delete.final_dialog_description') }}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>{{ t('common.cancel') }}</AlertDialogCancel>
                            <AlertDialogAction
                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                :disabled="destroyForm.processing"
                                @click="destroy"
                            >
                                {{ t('account.delete.final_confirm') }}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
                <InputError class="mt-2" :message="(destroyForm.errors as Record<string, string>).confirmation" />
            </template>

            <!-- State A: link already sent → check your email -->
            <template v-else-if="linkSent">
                <div
                    class="flex items-start gap-2 rounded-md border border-red-200 bg-white/60 p-3 text-sm text-red-700 dark:border-red-200/20 dark:bg-transparent dark:text-red-100"
                >
                    <MailCheck class="mt-0.5 size-4 shrink-0" />
                    <span>
                        {{ t('account.delete.check_email') }}
                    </span>
                </div>
                <Button variant="ghost" size="sm" class="text-red-600 hover:text-red-700 dark:text-red-100" @click="openDialog">
                    {{ t('account.delete.resend_link') }}
                </Button>
            </template>

            <!-- State A: initial → enter password -->
            <template v-else>
                <Button variant="destructive" @click="openDialog">
                    <Trash2 class="me-2 size-4" />
                    {{ t('account.delete.title') }}
                </Button>
            </template>
        </div>

        <!-- Password confirmation dialog (step 1) -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <form class="space-y-6" @submit="sendConfirmationLink">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{ t('account.delete.password_dialog_title') }}</DialogTitle>
                        <DialogDescription>{{ t('account.delete.password_dialog_description') }}</DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="password" class="sr-only">{{ t('confirm_password.password_label') }}</Label>
                        <div class="relative">
                            <Lock class="text-muted-foreground absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                            <Input
                                id="password"
                                ref="passwordInput"
                                v-model="passwordForm.password"
                                type="password"
                                name="password"
                                autocomplete="current-password"
                                :placeholder="t('account.delete.password_placeholder')"
                                class="ps-9"
                            />
                        </div>
                        <InputError :message="passwordForm.errors.password" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="secondary" @click="dialogOpen = false">{{ t('common.cancel') }}</Button>
                        <Button type="submit" variant="destructive" :disabled="passwordForm.processing">
                            <Loader2 v-if="passwordForm.processing" class="me-2 size-4 animate-spin" />
                            {{ t('account.delete.send_confirmation') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
