<script setup lang="ts">
// Identity verification for one agent: the public/protected switch, and while
// protected, the signing secret plus a guide to signing sign-in tokens.
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
import { update as updateSecurity } from '@/routes/agents/security';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import AccessControlCard from './AccessControlCard.vue';
import SignInTokensCard from './SignInTokensCard.vue';
import SigningSecretCard from './SigningSecretCard.vue';
import { useCopiedKey } from './useCopiedKey';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    auth_secret: string;
    embed_script_url: string;
}

const props = defineProps<Props>();

const { t } = useI18n();

const revealed = ref(false);
const { copiedKey, copy } = useCopiedKey();

// Access control — when protected, the widget refuses to load without a signed
// data-auth token. A failed save flips the switch back.
const protectionForm = useForm({ is_public: props.agent.is_public });
const isProtected = computed(() => !protectionForm.is_public);

const setPublic = (isPublic: boolean) => {
    protectionForm.is_public = isPublic;
    protectionForm.patch(updateSecurity.url(props.agent.external_id), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('agents.identity_verification.security_updated')),
        onError: () => {
            toast.error(t('agents.identity_verification.security_update_error'));
            protectionForm.is_public = !protectionForm.is_public;
        },
    });
};
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.identity_verification.title')" :description="t('agents.identity_verification.subtitle')">
        <div class="flex justify-center">
            <div class="w-full max-w-3xl space-y-6">
                <AccessControlCard :is-public="protectionForm.is_public" @change="setPublic" />

                <SigningSecretCard
                    v-if="isProtected"
                    v-model:revealed="revealed"
                    :agent-external-id="props.agent.external_id"
                    :auth-secret="props.auth_secret"
                    :copied-key="copiedKey"
                    @copy="copy"
                />

                <SignInTokensCard
                    v-if="isProtected"
                    :agent-external-id="props.agent.external_id"
                    :chat-url="props.agent.chat_url"
                    :embed-script-url="props.embed_script_url"
                    :copied-key="copiedKey"
                    @copy="copy"
                />
            </div>
        </div>
    </AgentLayout>
</template>
