<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios, { AxiosError } from 'axios';
import { Loader2, Lock, ShieldCheck, X } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const isOpen = ref(false);
const password = ref('');
const error = ref<string | null>(null);
const submitting = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);

let resolver: ((confirmed: boolean) => void) | null = null;

/**
 * Open the dialog and resolve true once the password is confirmed. Resolves
 * false if the user dismisses.
 *
 * Always shows the modal — we don't piggy-back on Fortify's 3-hour confirmed
 * cache because we want explicit consent on every security-sensitive action
 * (enable 2FA, register/delete passkey, etc.).
 */
const open = async (): Promise<boolean> => {
    return new Promise<boolean>((resolve) => {
        resolver = resolve;
        password.value = '';
        error.value = null;
        isOpen.value = true;
        nextTick(() => passwordInput.value?.focus());
    });
};

const submit = async () => {
    if (!password.value) {
        error.value = t('confirm_password.password_required');
        return;
    }
    submitting.value = true;
    error.value = null;
    try {
        await axios.post(route('password.confirm.store'), { password: password.value });
        isOpen.value = false;
        resolver?.(true);
        resolver = null;
    } catch (e: unknown) {
        const err = e as AxiosError<{ message?: string; errors?: { password?: string[] } }>;
        if (err.response?.status === 422) {
            error.value = err.response.data.errors?.password?.[0] ?? err.response.data.message ?? t('confirm_password.incorrect');
        } else {
            error.value = t('confirm_password.error');
        }
    } finally {
        submitting.value = false;
    }
};

const cancel = () => {
    isOpen.value = false;
    resolver?.(false);
    resolver = null;
};

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="isOpen" @update:open="(v) => !v && cancel()">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('confirm_password.title') }}</DialogTitle>
                <DialogDescription>{{ t('confirm_password.description') }}</DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="confirm-password">{{ t('confirm_password.password_label') }}</Label>
                    <div class="relative">
                        <Lock class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input
                            id="confirm-password"
                            ref="passwordInput"
                            v-model="password"
                            type="password"
                            autocomplete="current-password"
                            class="ps-9"
                            required
                        />
                    </div>
                    <p v-if="error" class="text-destructive text-sm">{{ error }}</p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" :disabled="submitting" @click="cancel">
                        <X class="me-2 size-4" />
                        {{ t('confirm_password.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="submitting">
                        <Loader2 v-if="submitting" class="me-2 size-4 animate-spin" />
                        <ShieldCheck v-else class="me-2 size-4" />
                        {{ t('confirm_password.confirm') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
