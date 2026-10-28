<script setup lang="ts">
// The agent's signing secret: masked by default, with reveal, copy and
// regenerate buttons. Regenerating invalidates every token already signed with
// the old secret, so it asks the user to type a confirmation phrase first.
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/shared/ui/alert-dialog';
import { Button } from '@/shared/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/ui/card';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/shared/ui/tooltip';
import { regenerate as regenerateSigningSecret } from '@/routes/agents/identity';
import { useForm } from '@inertiajs/vue3';
import { Check, Copy, Eye, EyeOff, RefreshCw } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agentExternalId: string;
    authSecret: string;
    copiedKey: string | null;
}>();

// Owned by the page so the reveal state survives the card being hidden while
// the agent is public.
const revealed = defineModel<boolean>('revealed', { required: true });

const emit = defineEmits<{ copy: [text: string, key: string] }>();

const { t } = useI18n();

const maskedSecret = computed(() => {
    const tail = props.authSecret.slice(-4);
    return `cvsk_${'•'.repeat(24)}${tail}`;
});

// Regenerating mints a brand-new secret server-side; the redirect back re-runs
// the page so it reloads with the new value.
const regenerateOpen = ref(false);
const regenerateForm = useForm({});

// Typing "I understand" arms the destructive action — a deliberate pause before
// invalidating every live token. Cleared whenever the dialog closes (cancel,
// escape, outside click, or success) so a reopen always starts fresh.
const regenerateConfirmation = ref('');
const confirmPhrase = computed(() => t('agents.identity_verification.confirm_phrase'));
const canRegenerate = computed(() => regenerateConfirmation.value.trim().toLowerCase() === confirmPhrase.value.toLowerCase());

watch(regenerateOpen, (open) => {
    if (!open) regenerateConfirmation.value = '';
});

const confirmRegenerate = () => {
    regenerateForm.post(regenerateSigningSecret.url(props.agentExternalId), {
        preserveScroll: true,
        onSuccess: () => {
            regenerateOpen.value = false;
            revealed.value = false;
            toast.success(t('agents.identity_verification.secret_regenerated'));
        },
        onError: () => toast.error(t('agents.identity_verification.regenerate_error')),
    });
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('agents.identity_verification.signing_secret_title') }}</CardTitle>
            <CardDescription>
                {{ t('agents.identity_verification.signing_secret_description') }}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex flex-col gap-2">
                <Label for="signing-secret">{{ t('agents.identity_verification.secret_label') }}</Label>
                <div class="flex items-center gap-2">
                    <Input id="signing-secret" :model-value="revealed ? props.authSecret : maskedSecret" readonly class="font-mono text-xs" />
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    :aria-label="revealed ? t('agents.identity_verification.hide') : t('agents.identity_verification.reveal')"
                                    @click="revealed = !revealed"
                                >
                                    <EyeOff v-if="revealed" class="size-4" />
                                    <Eye v-else class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{
                                revealed ? t('agents.identity_verification.hide') : t('agents.identity_verification.reveal')
                            }}</TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    :aria-label="t('agents.identity_verification.copy')"
                                    @click="emit('copy', props.authSecret, 'secret')"
                                >
                                    <Check v-if="copiedKey === 'secret'" class="size-4 text-green-600" />
                                    <Copy v-else class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{
                                copiedKey === 'secret' ? t('agents.identity_verification.copied') : t('agents.identity_verification.copy')
                            }}</TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    :aria-label="t('agents.identity_verification.regenerate_secret')"
                                    @click="regenerateOpen = true"
                                >
                                    <RefreshCw class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{ t('agents.identity_verification.regenerate_secret') }}</TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Regenerate confirmation -->
    <AlertDialog v-model:open="regenerateOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('agents.identity_verification.regenerate_dialog_title') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t('agents.identity_verification.regenerate_dialog_before') }}
                    <span class="font-medium">{{ t('agents.identity_verification.regenerate_dialog_emphasis') }}</span>
                    {{ t('agents.identity_verification.regenerate_dialog_after') }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <div class="grid gap-2">
                <Label for="regenerate-confirmation">{{
                    t('agents.identity_verification.regenerate_confirm_label', { phrase: confirmPhrase })
                }}</Label>
                <Input id="regenerate-confirmation" v-model="regenerateConfirmation" :placeholder="confirmPhrase" autocomplete="off" />
            </div>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('agents.identity_verification.cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    :disabled="!canRegenerate || regenerateForm.processing"
                    @click="confirmRegenerate"
                >
                    {{ t('agents.identity_verification.regenerate') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
