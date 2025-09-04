<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, User, Lock, Eye, EyeOff } from 'lucide-vue-next';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { ref } from 'vue';

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
        onError: (error) => {
            alert('Could not accept the invitation. Please try again.');
        }
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
        <div class="w-full max-w-lg mx-auto space-y-8">
            <!-- Status ChatMessage -->
            <div v-if="status" class="p-4 bg-green-50 dark:bg-green-950/50 border border-green-200 dark:border-green-800 rounded-lg">
                <p class="text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {{ status }}
                </p>
            </div>

            <!-- Password Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Name Field -->
                <div class="space-y-2">
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
                            placeholder="Enter your name"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <Label for="password" class="text-base font-medium">Password</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :tabindex="2"
                            autocomplete="new-password"
                            v-model="form.password"
                            placeholder="Create a secure password"
                            class="h-12 text-base pl-10 pr-10"
                        />
                        <button
                            type="button"
                            @click="togglePasswordVisibility"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
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
                    <Label for="password_confirmation" class="text-base font-medium">Confirm Password</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password_confirmation"
                            :type="showConfirmPassword ? 'text' : 'password'"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            placeholder="Confirm your password"
                            class="h-12 text-base pl-10 pr-10"
                        />
                        <button
                            type="button"
                            @click="toggleConfirmPasswordVisibility"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                            :tabindex="5"
                        >
                            <Eye v-if="!showConfirmPassword" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Submit Button -->
                <Button
                    type="submit"
                    class="w-full h-12 text-base font-semibold"
                    :tabindex="6"
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin mr-2" />
                    <span v-if="!form.processing">Accept Invitation</span>
                    <span v-else>Accepting Invitation...</span>
                </Button>
            </form>

            <!-- Back to Login Link -->
            <div class="text-center pt-4">
                <p class="text-base text-muted-foreground">
                    Already have an account?
                    <TextLink
                        :href="route('login')"
                        :tabindex="7"
                        class="font-semibold text-primary hover:text-primary/80 ml-1"
                    >
                        Sign in
                    </TextLink>
                </p>
            </div>
        </div>
    </AuthBase>
</template>
