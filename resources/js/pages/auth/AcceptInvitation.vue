<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, User, Lock, LockKeyhole, Users, Mail } from 'lucide-vue-next';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';

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
    form.post(route('teams.invitation.accept', { invitation: props.invitation.id }), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase 
        :title="userExists ? 'Login to accept invitation' : 'Create your account'" 
        :description="userExists ? 'Enter your password to join the team' : 'Fill in your details to join the team'"
    >
        <Head :title="userExists ? 'Accept Invitation' : 'Create Account & Join Team'" />

        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <div class="w-full max-w-lg mx-auto space-y-6">
            <!-- Invitation Message -->
            <div class="rounded-lg border bg-card p-6 text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center mb-4">
                    <Users class="h-7 w-7 text-primary" />
                </div>
                <p class="text-base text-muted-foreground leading-relaxed">
                    You've been invited to join <span class="font-semibold text-foreground">{{ team.name }}</span>
                    as {{ role.name.match(/^[aeiou]/i) ? 'an' : 'a' }} <span class="font-semibold text-foreground">{{ role.name }}</span>
                </p>
            </div>

            <!-- Registration/Login Form -->
            <form @submit.prevent="submit" class="space-y-5">
                <!-- Name Field (only for new users) -->
                <div v-if="!userExists" class="space-y-2">
                    <Label for="name" class="text-base font-medium">Name</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
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
                            placeholder="Your name"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Email Display (readonly) -->
                <div class="space-y-2">
                    <Label class="text-base font-medium">Email address</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <Mail class="h-5 w-5" />
                        </div>
                        <input
                            type="email"
                            :value="invitation.email"
                            disabled
                            class="flex h-12 w-full rounded-md border border-input bg-muted px-3 py-2 text-base pl-10 cursor-not-allowed ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">This is the email address the invitation was sent to</p>
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <Label for="password" class="text-base font-medium">
                        {{ userExists ? 'Your password' : 'Create a password' }}
                    </Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
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
                            :placeholder="userExists ? 'Enter your password' : 'Create a password'"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm Password Field (only for new users) -->
                <div v-if="!userExists" class="space-y-2">
                    <Label for="password_confirmation" class="text-base font-medium">Confirm password</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <LockKeyhole class="h-5 w-5" />
                        </div>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            placeholder="Confirm password"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Terms and Conditions (only for new users) -->
                <div v-if="!userExists" class="space-y-3">
                    <div class="flex items-start space-x-3">
                        <div class="flex items-center h-5">
                            <input
                                id="policy"
                                type="checkbox"
                                v-model="form.policy"
                                class="w-4 h-4 text-primary bg-background border-border rounded focus:ring-primary focus:ring-2 focus:ring-offset-0"
                                :tabindex="4"
                            />
                        </div>
                        <div class="text-sm">
                            <label for="policy" class="text-muted-foreground">
                                I agree to the
                                <a href="/privacy-policy" class="text-primary hover:underline" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
                            </label>
                            <InputError :message="form.errors.policy" />
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex items-center h-5">
                            <input
                                id="terms"
                                type="checkbox"
                                v-model="form.terms"
                                class="w-4 h-4 text-primary bg-background border-border rounded focus:ring-primary focus:ring-2 focus:ring-offset-0"
                                :tabindex="5"
                            />
                        </div>
                        <div class="text-sm">
                            <label for="terms" class="text-muted-foreground">
                                I agree to the
                                <a href="/terms-of-service" class="text-primary hover:underline" target="_blank" rel="noopener noreferrer">Terms of Service</a>
                            </label>
                            <InputError :message="form.errors.terms" />
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <Button
                    type="submit"
                    class="w-full h-12 text-base font-semibold"
                    :tabindex="userExists ? 2 : 6"
                    :disabled="form.processing || (!userExists && (!form.policy || !form.terms))"
                >
                    <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin mr-2" />
                    <span v-if="!form.processing">
                        {{ userExists ? 'Login & Join Team' : 'Create Account & Join Team' }}
                    </span>
                    <span v-else>
                        {{ userExists ? 'Logging in...' : 'Creating account...' }}
                    </span>
                </Button>

                <InputError :message="form.errors.invitation" class="text-center" />
            </form>
        </div>
    </AuthBase>
</template>

