<script setup lang="ts">
/**
 * Authentication part of the function form: the auth type select and the
 * secret fields that type needs (bearer token, header name + value, or HMAC
 * signing secret). When editing a function that already has a secret, an
 * empty field keeps the stored one — the placeholders say so.
 */
import InputError from '@/shared/components/InputError.vue';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/ui/select';
import { Key, KeyRound, Lock, Shield } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import type { FunctionAuthType } from './FunctionFormSheet.vue';

defineProps<{
    /** The function already stores a secret, so leaving the field empty keeps it. */
    keepSecretHint: boolean;
    headerNameError?: string;
    valueError?: string;
}>();
const authType = defineModel<FunctionAuthType>('authType', { required: true });
const headerName = defineModel<string>('headerName', { required: true });
const authValue = defineModel<string>('authValue', { required: true });

const { t } = useI18n();
</script>

<template>
    <div class="grid gap-2">
        <Label for="auth_type">{{ t('workspace.functions.form.auth') }}</Label>
        <Select v-model="authType">
            <SelectTrigger id="auth_type" class="w-full">
                <span class="flex min-w-0 items-center gap-2">
                    <Shield class="text-muted-foreground size-4" />
                    <SelectValue />
                </span>
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="none">{{ t('workspace.functions.form.auth_none') }}</SelectItem>
                <SelectItem value="bearer">{{ t('workspace.functions.form.auth_bearer') }}</SelectItem>
                <SelectItem value="header">{{ t('workspace.functions.form.auth_header') }}</SelectItem>
                <SelectItem value="hmac">{{ t('workspace.functions.form.auth_hmac') }}</SelectItem>
            </SelectContent>
        </Select>

        <div v-if="authType === 'bearer'" class="mt-2 grid gap-2">
            <Label for="auth_value">{{ t('workspace.functions.form.bearer_token') }}</Label>
            <div class="relative">
                <KeyRound class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                <Input
                    id="auth_value"
                    v-model="authValue"
                    type="password"
                    autocomplete="off"
                    :placeholder="keepSecretHint ? t('workspace.functions.form.keep_token') : 'sk-...'"
                    class="ps-9"
                />
            </div>
            <p class="text-muted-foreground text-xs">
                <i18n-t keypath="workspace.functions.form.bearer_hint" tag="span">
                    <template #header><code class="text-xs">Authorization: Bearer &lt;token&gt;</code></template>
                </i18n-t>
            </p>
            <InputError :message="valueError" />
        </div>

        <div v-else-if="authType === 'header'" class="mt-2 grid grid-cols-2 gap-3">
            <div class="grid gap-2">
                <Label for="auth_header_name">{{ t('workspace.functions.form.header_name') }}</Label>
                <div class="relative">
                    <Key class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                    <Input id="auth_header_name" v-model="headerName" placeholder="X-Api-Key" class="ps-9" />
                </div>
                <InputError :message="headerNameError" />
            </div>
            <div class="grid gap-2">
                <Label for="auth_value">{{ t('workspace.functions.form.header_value') }}</Label>
                <div class="relative">
                    <Lock class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                    <Input
                        id="auth_value"
                        v-model="authValue"
                        type="password"
                        autocomplete="off"
                        :placeholder="keepSecretHint ? t('workspace.functions.form.keep_value') : ''"
                        class="ps-9"
                    />
                </div>
                <InputError :message="valueError" />
            </div>
        </div>

        <div v-else-if="authType === 'hmac'" class="mt-2 grid gap-2">
            <Label for="auth_value">{{ t('workspace.functions.form.signing_secret') }}</Label>
            <div class="relative">
                <Lock class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                <Input
                    id="auth_value"
                    v-model="authValue"
                    type="password"
                    autocomplete="off"
                    :placeholder="keepSecretHint ? t('workspace.functions.form.keep_secret') : ''"
                    class="ps-9"
                />
            </div>
            <p class="text-muted-foreground text-xs">{{ t('workspace.functions.form.hmac_hint') }}</p>
            <InputError :message="valueError" />
        </div>
    </div>
</template>
