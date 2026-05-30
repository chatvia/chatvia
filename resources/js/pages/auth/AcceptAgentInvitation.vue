<script setup lang="ts">
import SocialLoginButtons from '@/components/auth/SocialLoginButtons.vue';
import InputError from '@/components/InputError.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { BotIcon, LoaderCircle, Lock, LockKeyhole, Mail, User } from 'lucide-vue-next';

interface Props {
    agent: {
        external_id: string;
        name: string;
    };
    email: string;
    accept_url: string;
    social_urls: {
        google: string;
        slack: string;
    };
}

const props = defineProps<Props>();

const form = useForm({
    email: props.email,
    name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(props.accept_url, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Accept your invitation" :description="`Set up your access to ${agent.name}`">
        <Head title="Accept invitation" />

        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <div class="mx-auto w-full max-w-lg space-y-6">
            <!-- Invitation header -->
            <div class="bg-card rounded-lg border p-6 text-center">
                <div class="bg-primary/10 mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full">
                    <BotIcon class="text-primary h-7 w-7" />
                </div>
                <p class="text-muted-foreground text-base leading-relaxed">
                    You've been invited to chat with <span class="text-foreground font-semibold">{{ agent.name }}</span>
                </p>
            </div>

            <!-- Password form -->
            <form @submit.prevent="submit" class="space-y-5">
                <!-- Name -->
                <div class="space-y-2">
                    <Label for="name" class="text-base font-medium">Your name</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
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
                            class="h-12 pl-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Email (locked to the invited address) -->
                <div class="space-y-2">
                    <Label class="text-base font-medium">Email address</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Mail class="h-5 w-5" />
                        </div>
                        <input
                            type="email"
                            :value="email"
                            disabled
                            class="border-input bg-muted ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-12 w-full cursor-not-allowed rounded-md border px-3 py-2 pl-10 text-base focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50"
                        />
                    </div>
                    <p class="text-muted-foreground text-xs">This invitation was sent to this address.</p>
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Password -->
                <div class="space-y-2">
                    <Label for="password" class="text-base font-medium">Create a password</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            :tabindex="2"
                            autocomplete="new-password"
                            v-model="form.password"
                            placeholder="Create a password"
                            class="h-12 pl-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm password -->
                <div class="space-y-2">
                    <Label for="password_confirmation" class="text-base font-medium">Confirm password</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
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
                            class="h-12 pl-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">Create account & start chatting</span>
                    <span v-else>Creating account...</span>
                </Button>

                <InputError :message="$page.props.errors?.invitation" class="text-center" />
            </form>

            <!-- Divider -->
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t"></span>
                </div>
                <div class="relative flex justify-center text-sm uppercase">
                    <span class="bg-background text-muted-foreground px-4 font-medium">Or</span>
                </div>
            </div>

            <!-- Social buttons — same component used on the login page, just
                 pointed at the invitation-specific OAuth-start URLs. -->
            <SocialLoginButtons :google-url="social_urls.google" :slack-url="social_urls.slack" />

            <p class="text-muted-foreground text-center text-xs">
                Make sure your social account uses <span class="text-foreground font-medium">{{ email }}</span> — it must match this invitation.
            </p>
        </div>
    </AuthBase>
</template>
