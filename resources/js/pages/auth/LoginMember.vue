<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail, Lock, Users } from 'lucide-vue-next';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import {onMounted, ref} from "vue";
import axios from "axios";
import { useFetchAppearance } from '@/composables/useAppearance';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    agentName?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const { appearance, fetchAppearance } = useFetchAppearance();

const submit = () => {
    form.post(route('chat.login'), {
        onFinish: () => form.reset('password'),
    });
};

onMounted(() => {
    fetchAppearance();
});
</script>

<template>
    <AuthBase :title="$page.props.agent.name" description="Sign in to access this chat workspace">
        <Head title="Member Login" />

        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="w-full max-w-lg mx-auto space-y-8">
            <div v-if="status" class="p-4 bg-green-50 dark:bg-green-950/50 border border-green-200 dark:border-green-800 rounded-lg">
                <p class="text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {{ status }}
                </p>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Email Field -->
                <div class="space-y-2">
                    <Label for="email" class="text-base font-medium">Email address</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <Mail class="h-5 w-5" />
                        </div>
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="member@company.com"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Password Field -->
                <div class="space-y-2">

                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="Enter your password"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Remember Me -->
                <div class="flex items-center space-x-2">
                    <Checkbox
                        id="remember"
                        v-model="form.remember"
                        :tabindex="3"
                    />
                    <Label for="remember" class="text-sm font-medium cursor-pointer">
                        Keep me signed in
                    </Label>
                </div>

                <!-- Login Button -->
                <Button
                    type="submit"
                    class="w-full h-12 text-base font-semibold"
                    :tabindex="4"
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin mr-2" />
                    <span v-if="!form.processing">Access Workspace</span>
                    <span v-else>Signing in...</span>
                </Button>
            </form>

            <!-- Member Information -->
            <div class="text-center pt-4 space-y-3">
                <div class="p-3 bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <Users class="h-4 w-4 inline mr-1" />
                        Team member access only. Contact your administrator if you need an account.
                    </p>
                </div>
            </div>
        </div>
    </AuthBase>
</template>
