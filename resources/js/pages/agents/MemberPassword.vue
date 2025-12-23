<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle, Lock, Mail, User } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    status?: string;
    agent: string;
    token: string;
    member: any;
}>();

const form = useForm({
    name: props.member.name,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const submit = () => {
    form.post(route('agents.invitations.accept', { agent: props.agent, token: props.token }), {
        onFinish: () => form.reset('password', 'password_confirmation'),
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
    <AuthBase title="Set your password" description="Create a secure password for your account">
        <Head title="Set Password" />

        <div class="absolute top-4 right-4">
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
                <div class="space-y-2">
                    <Label for="email" class="text-base font-medium">Email Address</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Mail class="h-5 w-5" />
                        </div>
                        <Input
                            id="email"
                            type="email"
                            v-model="props.member.email"
                            readonly
                            :tabindex="1"
                            class="bg-muted/50 h-12 cursor-not-allowed pl-10 text-base disabled:opacity-10"
                        />
                    </div>
                </div>

                <!-- Name Field -->
                <div class="space-y-2">
                    <Label for="name" class="text-base font-medium">Name</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <User class="h-5 w-5" />
                        </div>
                        <Input
                            id="name"
                            type="text"
                            required
                            autofocus
                            :tabindex="2"
                            autocomplete="name"
                            v-model="form.name"
                            :placeholder="props.member.name"
                            class="h-12 pl-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <Label for="password" class="text-base font-medium">Password</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            v-model="form.password"
                            placeholder="Create a secure password"
                            class="h-12 pr-10 pl-10 text-base"
                        />
                        <button
                            type="button"
                            @click="togglePasswordVisibility"
                            class="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 flex items-center pr-3"
                            tabindex="-1"
                        >
                            <Eye v-if="!showPassword" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm Password Field -->
                <div class="space-y-2">
                    <Label for="password_confirmation" class="text-base font-medium">Confirm Password</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password_confirmation"
                            :type="showConfirmPassword ? 'text' : 'password'"
                            required
                            :tabindex="5"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            placeholder="Confirm your password"
                            class="h-12 pr-10 pl-10 text-base"
                        />
                        <button
                            type="button"
                            @click="toggleConfirmPasswordVisibility"
                            class="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 flex items-center pr-3"
                            tabindex="-1"
                        >
                            <Eye v-if="!showConfirmPassword" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Submit Button -->
                <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="7" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">Accept Invitation</span>
                    <span v-else>Accepting Invitation...</span>
                </Button>
            </form>

            <!-- Back to Login Link -->
            <div class="pt-4 text-center">
                <p class="text-muted-foreground text-base">
                    Already have an account?
                    <TextLink :href="route('login')" :tabindex="7" class="text-primary hover:text-primary/80 ml-1 font-semibold"> Sign in </TextLink>
                </p>
            </div>
        </div>
    </AuthBase>
</template>
