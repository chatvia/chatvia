<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { update as updateSecuritySettings } from '@/routes/agents/security/settings';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    securitySettings: {
        chat_rate_limit_per_minute: number;
        notify_owner_on_rate_limit: boolean;
        visitor_rate_limit_per_minute: number;
    };
}

const props = defineProps<Props>();

// Per-agent chat rate limit — caps answered messages per minute across all
// visitors, with an optional email alert to the owner when it trips — and a
// smaller cap per visitor, so one person cannot use up the agent's cap.
const form = useForm({
    chat_rate_limit_per_minute: props.securitySettings.chat_rate_limit_per_minute,
    notify_owner_on_rate_limit: props.securitySettings.notify_owner_on_rate_limit,
    visitor_rate_limit_per_minute: props.securitySettings.visitor_rate_limit_per_minute,
});

const save = () => {
    form.post(updateSecuritySettings.url(props.agent.external_id), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('agents.security.rate_limiting.saved')),
        onError: () => toast.error(t('agents.security.rate_limiting.save_error')),
    });
};
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.security.rate_limiting.title')" :description="t('agents.security.rate_limiting.subtitle')">
        <div class="flex justify-center">
            <div class="w-full max-w-3xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('agents.security.rate_limiting.messages_per_minute') }}</CardTitle>
                        <CardDescription>
                            {{ t('agents.security.rate_limiting.card_description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex flex-col gap-2">
                            <Label for="rate-limit">{{ t('agents.security.rate_limiting.messages_per_minute') }}</Label>
                            <Input
                                id="rate-limit"
                                v-model.number="form.chat_rate_limit_per_minute"
                                type="number"
                                min="1"
                                max="1000"
                                class="max-w-[160px]"
                            />
                            <p v-if="form.errors.chat_rate_limit_per_minute" class="text-destructive text-xs">
                                {{ form.errors.chat_rate_limit_per_minute }}
                            </p>
                            <p v-else class="text-muted-foreground text-xs">{{ t('agents.security.rate_limiting.default_hint') }}</p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="visitor-rate-limit">{{ t('agents.security.rate_limiting.visitor_messages_per_minute') }}</Label>
                            <Input
                                id="visitor-rate-limit"
                                v-model.number="form.visitor_rate_limit_per_minute"
                                type="number"
                                min="1"
                                max="1000"
                                class="max-w-[160px]"
                            />
                            <p v-if="form.errors.visitor_rate_limit_per_minute" class="text-destructive text-xs">
                                {{ form.errors.visitor_rate_limit_per_minute }}
                            </p>
                            <p v-else class="text-muted-foreground text-xs">{{ t('agents.security.rate_limiting.visitor_hint') }}</p>
                        </div>

                        <label for="notify-rate-limit" class="flex cursor-pointer items-center justify-between rounded-lg border p-4">
                            <div class="space-y-0.5 pe-4">
                                <span class="text-sm leading-none font-medium">{{ t('agents.security.rate_limiting.notify_label') }}</span>
                                <p class="text-muted-foreground text-xs">{{ t('agents.security.rate_limiting.notify_hint') }}</p>
                            </div>
                            <Switch id="notify-rate-limit" v-model="form.notify_owner_on_rate_limit" />
                        </label>

                        <div class="flex justify-end">
                            <Button :disabled="form.processing" @click="save">{{ t('agents.security.rate_limiting.save') }}</Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AgentLayout>
</template>
