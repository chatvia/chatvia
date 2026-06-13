<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle, Lock, User } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps<{
    status?: string;
    email?: string;
    token?: string;
    agent?: any;
}>();

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const submit = () => {
    form.post(route('agents.invitations.accept'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
        onError: (_error) => {
            alert(t('auth.member_password.accept_error'));
        },
    });
};

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};

const toggleConfirmPasswordVisibility = () => {
    showConfirmPassword.value = !showConfirmPassword.value;
};
</script>

<template>
    <AuthBase :title="t('auth.member_password.title')" :description="t('auth.member_password.description')">
        <Head :title="t('auth.member_password.head')" />

        <div class="absolute end-4 top-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="mx-auto w-full max-w-lg space-y-8">
            <!-- Status ChatMessage -->
            <div v-if="status" class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/50">
                <p class="text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {{ status }}
                </p>
            </div>

            <!-- Password Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Name Field -->
                <div class="space-y-2">
                    <Label for="name" class="text-base font-medium">{{ t('auth.member_password.name_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <User class="h-5 w-5" />
                        </div>
                        <Input
                            id="name"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="name"
                            v-model="form.name"
                            :placeholder="t('auth.member_password.name_placeholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <Label for="password" class="text-base font-medium">{{ t('auth.member_password.password_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :tabindex="2"
                            autocomplete="new-password"
                            v-model="form.password"
                            :placeholder="t('auth.member_password.password_placeholder')"
                            class="h-12 ps-10 pe-10 text-base"
                        />
                        <button
                            type="button"
                            @click="togglePasswordVisibility"
                            class="text-muted-foreground hover:text-foreground absolute inset-y-0 end-0 flex items-center pe-3"
                            :tabindex="3"
                        >
                            <Eye v-if="!showPassword" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm Password Field -->
                <div class="space-y-2">
                    <Label for="password_confirmation" class="text-base font-medium">{{ t('auth.member_password.confirm_password_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password_confirmation"
                            :type="showConfirmPassword ? 'text' : 'password'"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            :placeholder="t('auth.member_password.confirm_password_placeholder')"
                            class="h-12 ps-10 pe-10 text-base"
                        />
                        <button
                            type="button"
                            @click="toggleConfirmPasswordVisibility"
                            class="text-muted-foreground hover:text-foreground absolute inset-y-0 end-0 flex items-center pe-3"
                            :tabindex="5"
                        >
                            <Eye v-if="!showConfirmPassword" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Submit Button -->
                <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="6" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="me-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">{{ t('auth.member_password.accept_invitation') }}</span>
                    <span v-else>{{ t('auth.member_password.accepting_invitation') }}</span>
                </Button>
            </form>

            <!-- Back to Login Link -->
            <div class="pt-4 text-center">
                <p class="text-muted-foreground text-base">
                    {{ t('auth.member_password.have_account') }}
                    <TextLink :href="route('login')" :tabindex="7" class="text-primary hover:text-primary/80 ms-1 font-semibold">
                        {{ t('auth.member_password.sign_in') }}
                    </TextLink>
                </p>
            </div>
        </div>
    </AuthBase>
</template>
