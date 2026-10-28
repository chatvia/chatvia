<script setup lang="ts">
import InputError from '@/shared/components/InputError.vue';
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import AuthLayout from '@/shared/layouts/AuthLayout.vue';
import { store as twoFactorLogin } from '@/routes/two-factor/login';
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound, LoaderCircle, ShieldCheck } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

type Mode = 'code' | 'recovery';
const mode = ref<Mode>('code');

const codeInput = ref<HTMLInputElement | null>(null);
const recoveryInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    code: '',
    recovery_code: '',
});

const submit = () => {
    form.post(twoFactorLogin.url(), {
        onFinish: () => form.reset(),
    });
};

const switchTo = async (next: Mode) => {
    mode.value = next;
    form.clearErrors();
    await nextTick();
    (next === 'code' ? codeInput : recoveryInput).value?.focus();
};
</script>

<template>
    <AuthLayout :title="t('auth.two_factor.title')" :description="t('auth.two_factor.description')">
        <Head :title="t('auth.two_factor.head')" />

        <form @submit.prevent="submit" class="space-y-6">
            <div v-if="mode === 'code'" class="space-y-2">
                <Label for="code" class="text-base font-medium">{{ t('auth.two_factor.code_label') }}</Label>
                <div class="relative">
                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                        <ShieldCheck class="h-5 w-5" />
                    </div>
                    <Input
                        id="code"
                        ref="codeInput"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        required
                        autofocus
                        v-model="form.code"
                        :placeholder="t('auth.two_factor.code_placeholder')"
                        class="h-12 ps-10 text-base tracking-widest"
                    />
                </div>
                <InputError :message="form.errors.code" />
            </div>

            <div v-else class="space-y-2">
                <Label for="recovery_code" class="text-base font-medium">{{ t('auth.two_factor.recovery_code_label') }}</Label>
                <div class="relative">
                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                        <KeyRound class="h-5 w-5" />
                    </div>
                    <Input
                        id="recovery_code"
                        ref="recoveryInput"
                        type="text"
                        autocomplete="one-time-code"
                        required
                        v-model="form.recovery_code"
                        :placeholder="t('auth.two_factor.recovery_code_placeholder')"
                        class="h-12 ps-10 text-base"
                    />
                </div>
                <InputError :message="form.errors.recovery_code" />
            </div>

            <Button type="submit" class="h-12 w-full text-base font-semibold" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="me-2 h-5 w-5 animate-spin" />
                <span v-if="!form.processing">{{ t('auth.two_factor.verify') }}</span>
                <span v-else>{{ t('auth.two_factor.verifying') }}</span>
            </Button>

            <div class="text-center">
                <button
                    v-if="mode === 'code'"
                    type="button"
                    @click="switchTo('recovery')"
                    class="text-primary hover:text-primary/80 text-sm font-medium"
                >
                    {{ t('auth.two_factor.use_recovery_code') }}
                </button>
                <button v-else type="button" @click="switchTo('code')" class="text-primary hover:text-primary/80 text-sm font-medium">
                    {{ t('auth.two_factor.use_authenticator_code') }}
                </button>
            </div>
        </form>
    </AuthLayout>
</template>
