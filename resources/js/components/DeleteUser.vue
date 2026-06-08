<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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
import { AlertTriangle, Bot, CreditCard, Download, Loader2, Lock, MailCheck, ShieldAlert, Trash2 } from 'lucide-vue-next';

interface Props {
    agents: { name: string }[];
    hasSubscription: boolean;
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

    passwordForm.post(route('settings.account.delete.request'), {
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
    destroyForm.delete(route('settings.account.destroy'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                <Trash2 class="size-6" />
                Delete account
            </h1>
            <p class="text-muted-foreground mt-1 text-sm">Permanently delete your account and everything in it.</p>
        </div>

        <Separator />

        <!-- Reminder: download a copy of your data first -->
        <div class="flex items-start gap-3 rounded-lg border p-4">
            <Download class="text-muted-foreground mt-0.5 size-5 shrink-0" />
            <div class="space-y-1">
                <p class="text-sm font-medium">Want a copy of your data first?</p>
                <p class="text-muted-foreground text-sm">
                    Once your account is gone, your data is gone with it. You can
                    <Link href="/settings/data-export" class="text-foreground font-medium underline underline-offset-4">download an archive</Link>
                    of everything we hold about you before you delete.
                </p>
            </div>
        </div>

        <!-- Danger zone -->
        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
            <div class="flex items-start gap-3 text-red-600 dark:text-red-100">
                <AlertTriangle class="mt-0.5 size-5 shrink-0" />
                <div class="space-y-1">
                    <p class="font-medium">This cannot be undone</p>
                    <p class="text-sm">Deleting your account permanently removes everything in it, including:</p>
                </div>
            </div>

            <ul class="ml-8 list-disc space-y-1 text-sm text-red-600 dark:text-red-100">
                <li>Your agents and their settings</li>
                <li>All conversations and messages</li>
                <li>Knowledge sources, documents, and uploaded files</li>
                <li>Your team, members, API keys, and connectors</li>
            </ul>

            <!-- Agents that will be deleted -->
            <div v-if="props.agents.length" class="ml-8 space-y-2">
                <p class="flex items-center gap-2 text-sm font-medium text-red-600 dark:text-red-100">
                    <Bot class="size-4 shrink-0" />
                    {{ props.agents.length }} {{ props.agents.length === 1 ? 'agent' : 'agents' }} will be permanently deleted:
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

            <!-- Subscription cancellation notice -->
            <div
                v-if="props.hasSubscription"
                class="ml-8 flex items-start gap-2 rounded-md bg-red-100/60 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-100"
            >
                <CreditCard class="mt-0.5 size-4 shrink-0" />
                <span>Your active subscription will be canceled and you won't be billed again.</span>
            </div>

            <!-- State B: email confirmed → final, explicit delete -->
            <template v-if="props.confirmed">
                <div
                    class="flex items-start gap-2 rounded-md border border-red-200 bg-white/60 p-3 text-sm text-red-700 dark:border-red-200/20 dark:bg-transparent dark:text-red-100"
                >
                    <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                    <span>Email confirmed. This is your last step — there's no going back after this.</span>
                </div>

                <AlertDialog>
                    <AlertDialogTrigger as-child>
                        <Button variant="destructive">
                            <Trash2 class="mr-2 size-4" />
                            Permanently delete my account
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Permanently delete your account?</AlertDialogTitle>
                            <AlertDialogDescription>
                                This will permanently delete your account and all of its data, and cannot be undone.
                                {{ props.hasSubscription ? 'Your subscription will also be canceled.' : '' }}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                :disabled="destroyForm.processing"
                                @click="destroy"
                            >
                                Yes, delete everything
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
                        Check your email — we sent a confirmation link to finish deleting your account. It expires in 30 minutes. Open the link to
                        continue.
                    </span>
                </div>
                <Button variant="ghost" size="sm" class="text-red-600 hover:text-red-700 dark:text-red-100" @click="openDialog">
                    Send the link again
                </Button>
            </template>

            <!-- State A: initial → enter password -->
            <template v-else>
                <Button variant="destructive" @click="openDialog">
                    <Trash2 class="mr-2 size-4" />
                    Delete account
                </Button>
            </template>
        </div>

        <!-- Password confirmation dialog (step 1) -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <form class="space-y-6" @submit="sendConfirmationLink">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Confirm your password</DialogTitle>
                        <DialogDescription>
                            Enter your password to continue. We'll email you a confirmation link to finish deleting your account.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="password" class="sr-only">Password</Label>
                        <div class="relative">
                            <Lock class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                id="password"
                                ref="passwordInput"
                                v-model="passwordForm.password"
                                type="password"
                                name="password"
                                autocomplete="current-password"
                                placeholder="Password"
                                class="pl-9"
                            />
                        </div>
                        <InputError :message="passwordForm.errors.password" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="secondary" @click="dialogOpen = false">Cancel</Button>
                        <Button type="submit" variant="destructive" :disabled="passwordForm.processing">
                            <Loader2 v-if="passwordForm.processing" class="mr-2 size-4 animate-spin" />
                            Send confirmation email
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
