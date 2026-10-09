<script setup lang="ts">
// The "make this agent public" switch. While protected, the widget refuses to
// load without a signed `data-auth` token. Going public exposes the agent to
// anyone, so that direction asks for confirmation first and the switch stays
// off until the user confirms; going protected applies at once.
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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{ isPublic: boolean }>();

const emit = defineEmits<{ change: [isPublic: boolean] }>();

const { t } = useI18n();

const makePublicOpen = ref(false);

const onToggle = (next: boolean) => {
    if (next) {
        makePublicOpen.value = true;
    } else {
        emit('change', false);
    }
};

const confirmMakePublic = () => {
    makePublicOpen.value = false;
    emit('change', true);
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('agents.identity_verification.access_control') }}</CardTitle>
            <CardDescription>
                {{ t('agents.identity_verification.access_control_description_before') }}
                <code>data-auth</code> {{ t('agents.identity_verification.access_control_description_after') }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <label for="agent-public" class="flex cursor-pointer items-center justify-between rounded-lg border p-4">
                <div class="space-y-0.5 pe-4">
                    <span class="text-sm leading-none font-medium">{{ t('agents.identity_verification.make_public_label') }}</span>
                    <p class="text-muted-foreground text-xs">
                        {{ t('agents.identity_verification.make_public_hint') }}
                    </p>
                </div>
                <Switch id="agent-public" :model-value="isPublic" @update:model-value="onToggle" />
            </label>
        </CardContent>
    </Card>

    <!-- Make public confirmation — flipping to public exposes the agent so we double-check -->
    <AlertDialog v-model:open="makePublicOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('agents.identity_verification.make_public_dialog_title') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t('agents.identity_verification.make_public_dialog_description') }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('agents.identity_verification.cancel') }}</AlertDialogCancel>
                <AlertDialogAction @click="confirmMakePublic">{{ t('agents.identity_verification.make_public_confirm') }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
