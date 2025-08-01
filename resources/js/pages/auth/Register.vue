<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, User, Mail, Lock, LockKeyhole } from 'lucide-vue-next';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    policy: false,
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Create an account" description="Enter your details below to create your account">
        <Head title="Register" />

        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="w-full max-w-lg mx-auto space-y-8">
            <!-- Registration Form -->
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
                            placeholder="Full name"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

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
                            :tabindex="2" 
                            autocomplete="email" 
                            v-model="form.email" 
                            placeholder="email@example.com"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
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
                            type="password"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            v-model="form.password"
                            placeholder="Password"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm Password Field -->
                <div class="space-y-2">
                    <Label for="password_confirmation" class="text-base font-medium">Confirm password</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <LockKeyhole class="h-5 w-5" />
                        </div>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            placeholder="Confirm password"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Terms and Conditions -->
                <div class="space-y-3">
                    <div class="flex items-start space-x-3">
                        <div class="flex items-center h-5">
                            <input
                                id="policy"
                                type="checkbox"
                                v-model="form.policy"
                                class="w-4 h-4 text-primary bg-background border-border rounded focus:ring-primary focus:ring-2 focus:ring-offset-0"
                                :tabindex="5"
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
                                :tabindex="6"
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

                <!-- Register Button -->
                <Button
                    type="submit"
                    class="w-full h-12 text-base font-semibold"
                    :tabindex="7"
                    :disabled="form.processing || !form.policy || !form.terms"
                >
                    <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin mr-2" />
                    <span v-if="!form.processing">Create account</span>
                    <span v-else>Creating account...</span>
                </Button>
            </form>

            <!-- Sign In Link -->
            <div class="text-center pt-4">
                <p class="text-base text-muted-foreground">
                    Already have an account?
                    <TextLink
                        :href="route('login')"
                        :tabindex="8"
                        class="font-semibold text-primary hover:text-primary/80 ml-1"
                    >
                        Log in
                    </TextLink>
                </p>
            </div>
        </div>
    </AuthBase>
</template>
