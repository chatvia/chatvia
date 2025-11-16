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

const loginWithGoogle = () => {
    window.location.href = route('auth.google');
}

const loginWithSlack = () => {
    window.location.href = route('auth.slack');
}

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
                            placeholder="John Doe"
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

            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t"></span>
                </div>
                <div class="relative flex justify-center text-sm uppercase">
                    <span class="bg-background px-4 text-muted-foreground font-medium">Or</span>
                </div>
            </div>

            <!-- Social Login Buttons -->
            <div class="space-y-3">
                <Button
                    type="button"
                    variant="outline"
                    @click="loginWithGoogle"
                    class="w-full h-12 text-base font-medium"
                    :tabindex="6"
                >
                    <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    Continue with Google
                </Button>
                
                <Button
                    type="button"
                    variant="outline"
                    @click="loginWithSlack"
                    class="w-full h-12 text-base font-medium"
                    :tabindex="7"
                >
                    <svg class="h-5 w-5 mr-2" viewBox="0 0 127 127" xmlns="http://www.w3.org/2000/svg">
                        <path d="M27.2 80c0 7.3-5.9 13.2-13.2 13.2C6.7 93.2.8 87.3.8 80c0-7.3 5.9-13.2 13.2-13.2h13.2V80zm6.6 0c0-7.3 5.9-13.2 13.2-13.2 7.3 0 13.2 5.9 13.2 13.2v33c0 7.3-5.9 13.2-13.2 13.2-7.3 0-13.2-5.9-13.2-13.2V80z" fill="#E01E5A"/>
                        <path d="M47 27c-7.3 0-13.2-5.9-13.2-13.2C33.8 6.5 39.7.6 47 .6c7.3 0 13.2 5.9 13.2 13.2V27H47zm0 6.7c7.3 0 13.2 5.9 13.2 13.2 0 7.3-5.9 13.2-13.2 13.2H13.9C6.6 60.1.7 54.2.7 46.9c0-7.3 5.9-13.2 13.2-13.2H47z" fill="#36C5F0"/>
                        <path d="M99.9 46.9c0-7.3 5.9-13.2 13.2-13.2 7.3 0 13.2 5.9 13.2 13.2 0 7.3-5.9 13.2-13.2 13.2H99.9V46.9zm-6.6 0c0 7.3-5.9 13.2-13.2 13.2-7.3 0-13.2-5.9-13.2-13.2V13.8C66.9 6.5 72.8.6 80.1.6c7.3 0 13.2 5.9 13.2 13.2v33.1z" fill="#2EB67D"/>
                        <path d="M80.1 99.8c7.3 0 13.2 5.9 13.2 13.2 0 7.3-5.9 13.2-13.2 13.2-7.3 0-13.2-5.9-13.2-13.2V99.8h13.2zm0-6.6c-7.3 0-13.2-5.9-13.2-13.2 0-7.3 5.9-13.2 13.2-13.2h33.1c7.3 0 13.2 5.9 13.2 13.2 0 7.3-5.9 13.2-13.2 13.2H80.1z" fill="#ECB22E"/>
                    </svg>
                    Continue with Slack
                </Button>
            </div>

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
