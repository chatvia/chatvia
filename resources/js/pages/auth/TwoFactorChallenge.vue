<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound, LoaderCircle, ShieldCheck } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

type Mode = 'code' | 'recovery';
const mode = ref<Mode>('code');

const codeInput = ref<HTMLInputElement | null>(null);
const recoveryInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    code: '',
    recovery_code: '',
});

const submit = () => {
    form.post(route('two-factor.login.store'), {
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
    <AuthLayout title="Two-factor authentication" description="Enter the code from your authenticator app to continue.">
        <Head title="Two-factor challenge" />

        <form @submit.prevent="submit" class="space-y-6">
            <div v-if="mode === 'code'" class="space-y-2">
                <Label for="code" class="text-base font-medium">Authentication code</Label>
                <div class="relative">
                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
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
                        placeholder="123456"
                        class="h-12 pl-10 text-base tracking-widest"
                    />
                </div>
                <InputError :message="form.errors.code" />
            </div>

            <div v-else class="space-y-2">
                <Label for="recovery_code" class="text-base font-medium">Recovery code</Label>
                <div class="relative">
                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <KeyRound class="h-5 w-5" />
                    </div>
                    <Input
                        id="recovery_code"
                        ref="recoveryInput"
                        type="text"
                        autocomplete="one-time-code"
                        required
                        v-model="form.recovery_code"
                        placeholder="xxxxxxxx-xxxxxxxx"
                        class="h-12 pl-10 text-base"
                    />
                </div>
                <InputError :message="form.errors.recovery_code" />
            </div>

            <Button type="submit" class="h-12 w-full text-base font-semibold" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                <span v-if="!form.processing">Verify</span>
                <span v-else>Verifying...</span>
            </Button>

            <div class="text-center">
                <button
                    v-if="mode === 'code'"
                    type="button"
                    @click="switchTo('recovery')"
                    class="text-primary hover:text-primary/80 text-sm font-medium"
                >
                    Use a recovery code
                </button>
                <button
                    v-else
                    type="button"
                    @click="switchTo('code')"
                    class="text-primary hover:text-primary/80 text-sm font-medium"
                >
                    Use an authenticator code
                </button>
            </div>
        </form>
    </AuthLayout>
</template>
