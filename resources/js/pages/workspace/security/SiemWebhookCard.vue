<script setup lang="ts">
// SIEM webhook card: streams security events to the customer's own SIEM. The
// signing secret is write-only — a blank field keeps the stored secret.
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Webhook } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    canManage: boolean;
    errors: { webhook_url?: string; webhook_secret?: string };
}>();

const enabled = defineModel<boolean>('enabled', { required: true });
const url = defineModel<string>('url', { required: true });
const secret = defineModel<string>('secret', { required: true });

const { t } = useI18n();

const toggle = () => {
    if (props.canManage) enabled.value = !enabled.value;
};
</script>

<template>
    <Card class="overflow-hidden py-0">
        <CardContent class="p-0">
            <div class="flex items-start justify-between gap-4 p-5" :class="{ 'cursor-pointer': canManage }" @click="toggle">
                <div class="flex items-start gap-3">
                    <div class="bg-background text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg border">
                        <Webhook class="size-5" />
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-sm font-semibold">{{ t('workspace.security.siem.title') }}</p>
                        <p class="text-muted-foreground text-sm">{{ t('workspace.security.siem.help') }}</p>
                    </div>
                </div>
                <Switch
                    :model-value="enabled"
                    :disabled="!canManage"
                    :aria-label="t('workspace.security.siem.title')"
                    class="pointer-events-none mt-0.5"
                />
            </div>

            <div v-if="enabled" class="space-y-4 border-t p-5">
                <div class="space-y-1.5">
                    <Label for="siem-url">{{ t('workspace.security.siem.url') }}</Label>
                    <div class="relative">
                        <Webhook class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input
                            id="siem-url"
                            v-model="url"
                            type="url"
                            :disabled="!canManage"
                            placeholder="https://siem.example.com/ingest"
                            class="ps-9 font-mono"
                        />
                    </div>
                    <p v-if="errors.webhook_url" class="text-destructive text-xs">{{ errors.webhook_url }}</p>
                </div>

                <div class="space-y-1.5">
                    <Label for="siem-secret">{{ t('workspace.security.siem.secret') }}</Label>
                    <Input
                        id="siem-secret"
                        v-model="secret"
                        type="password"
                        autocomplete="new-password"
                        :disabled="!canManage"
                        :placeholder="t('workspace.security.siem.secret_placeholder')"
                    />
                    <p class="text-muted-foreground text-xs">{{ t('workspace.security.siem.secret_help') }}</p>
                    <p v-if="errors.webhook_secret" class="text-destructive text-xs">{{ errors.webhook_secret }}</p>
                </div>

                <p class="text-muted-foreground text-xs">{{ t('workspace.security.siem.signature_help') }}</p>
            </div>
        </CardContent>
    </Card>
</template>
