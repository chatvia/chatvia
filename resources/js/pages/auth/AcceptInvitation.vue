<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, LockKeyhole, Mail, User, Users } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    invitation: {
        id: number;
        email: string;
    };
    team: {
        id: number;
        name: string;
    };
    inviter: {
        name: string;
        email: string;
    };
    role: {
        code: string;
        name: string;
    };
    userExists: boolean;
    acceptUrl: string;
}

const props = defineProps<Props>();

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
    policy: false,
    terms: false,
});

const submit = () => {
    form.post(props.acceptUrl, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase
        :title="userExists ? t('auth.accept_invitation.title_existing') : t('auth.accept_invitation.title_new')"
        :description="userExists ? t('auth.accept_invitation.description_existing') : t('auth.accept_invitation.description_new')"
    >
        <Head :title="userExists ? t('auth.accept_invitation.head_existing') : t('auth.accept_invitation.head_new')" />

        <div class="absolute top-4 end-4">
            <ThemeSwitcher />
        </div>

        <div class="mx-auto w-full max-w-lg space-y-6">
            <!-- Invitation Message -->
            <div class="bg-card rounded-lg border p-6 text-center">
                <div class="bg-primary/10 mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full">
                    <Users class="text-primary h-7 w-7" />
                </div>
                <p class="text-muted-foreground text-base leading-relaxed">
                    {{ t('auth.accept_invitation.invited_to_join') }} <span class="text-foreground font-semibold">{{ team.name }}</span>
                    {{ t('auth.accept_invitation.as') }} <span class="text-foreground font-semibold">{{ role.name }}</span>
                </p>
            </div>

            <!-- Registration/Login Form -->
            <form @submit.prevent="submit" class="space-y-5">
                <!-- Name Field (only for new users) -->
                <div v-if="!userExists" class="space-y-2">
                    <Label for="name" class="text-base font-medium">{{ t('auth.accept_invitation.name_label') }}</Label>
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
                            :placeholder="t('auth.accept_invitation.name_placeholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Email Display (readonly) -->
                <div class="space-y-2">
                    <Label class="text-base font-medium">{{ t('auth.accept_invitation.email_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <Mail class="h-5 w-5" />
                        </div>
                        <input
                            type="email"
                            :value="invitation.email"
                            disabled
                            class="border-input bg-muted ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-12 w-full cursor-not-allowed rounded-md border px-3 py-2 ps-10 text-base file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50"
                        />
                    </div>
                    <p class="text-muted-foreground text-xs">{{ t('auth.accept_invitation.email_hint') }}</p>
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <Label for="password" class="text-base font-medium">
                        {{ userExists ? t('auth.accept_invitation.password_label_existing') : t('auth.accept_invitation.password_label_new') }}
                    </Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            :autofocus="userExists"
                            :tabindex="userExists ? 1 : 2"
                            :autocomplete="userExists ? 'current-password' : 'new-password'"
                            v-model="form.password"
                            :placeholder="userExists ? t('auth.accept_invitation.password_placeholder_existing') : t('auth.accept_invitation.password_placeholder_new')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm Password Field (only for new users) -->
                <div v-if="!userExists" class="space-y-2">
                    <Label for="password_confirmation" class="text-base font-medium">{{ t('auth.accept_invitation.confirm_password_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <LockKeyhole class="h-5 w-5" />
                        </div>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            :placeholder="t('auth.accept_invitation.confirm_password_placeholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Terms and Conditions (only for new users) -->
                <div v-if="!userExists" class="space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="flex h-5 items-center">
                            <input
                                id="policy"
                                type="checkbox"
                                v-model="form.policy"
                                class="text-primary bg-background border-border focus:ring-primary h-4 w-4 rounded focus:ring-2 focus:ring-offset-0"
                                :tabindex="4"
                            />
                        </div>
                        <div class="text-sm">
                            <label for="policy" class="text-muted-foreground">
                                {{ t('auth.accept_invitation.agree_to') }}
                                <a href="/privacy-policy" class="text-primary hover:underline" target="_blank" rel="noopener noreferrer"
                                    >{{ t('auth.accept_invitation.privacy_policy') }}</a
                                >
                            </label>
                            <InputError :message="form.errors.policy" />
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="flex h-5 items-center">
                            <input
                                id="terms"
                                type="checkbox"
                                v-model="form.terms"
                                class="text-primary bg-background border-border focus:ring-primary h-4 w-4 rounded focus:ring-2 focus:ring-offset-0"
                                :tabindex="5"
                            />
                        </div>
                        <div class="text-sm">
                            <label for="terms" class="text-muted-foreground">
                                {{ t('auth.accept_invitation.agree_to') }}
                                <a href="/terms-of-service" class="text-primary hover:underline" target="_blank" rel="noopener noreferrer"
                                    >{{ t('auth.accept_invitation.terms_of_service') }}</a
                                >
                            </label>
                            <InputError :message="form.errors.terms" />
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <Button
                    type="submit"
                    class="h-12 w-full text-base font-semibold"
                    :tabindex="userExists ? 2 : 6"
                    :disabled="form.processing || (!userExists && (!form.policy || !form.terms))"
                >
                    <LoaderCircle v-if="form.processing" class="me-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">
                        {{ userExists ? t('auth.accept_invitation.submit_existing') : t('auth.accept_invitation.submit_new') }}
                    </span>
                    <span v-else>
                        {{ userExists ? t('auth.accept_invitation.logging_in') : t('auth.accept_invitation.creating_account') }}
                    </span>
                </Button>

                <!-- The `invitation` error is set server-side (token validity)
                     and lives on $page.props.errors rather than the form-bound keys. -->
                <InputError :message="$page.props.errors?.invitation" class="text-center" />
            </form>
        </div>
    </AuthBase>
</template>
