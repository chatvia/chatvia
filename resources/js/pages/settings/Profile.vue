<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';
import { getInitials } from '@/composables/useInitials';
import { CheckCircle, User as UserIcon, Mail, Save } from 'lucide-vue-next';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-8">
                <!-- Profile Header with Avatar -->
                <div class="flex flex-col items-center space-y-4 sm:flex-row sm:space-x-6 sm:space-y-0">
                    <Avatar class="size-24 border-4 border-background shadow-md">
                        <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                        <AvatarFallback class="text-xl font-semibold bg-primary/10 text-primary">
                            {{ getInitials(user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="space-y-1 text-center sm:text-left">
                        <h1 class="text-2xl font-bold tracking-tight">{{ user.name }}</h1>
                        <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                            <p class="text-sm text-muted-foreground flex items-center">
                                <Mail class="mr-1 size-3.5" />
                                {{ user.email }}
                            </p>
                            <Badge v-if="user.email_verified_at" variant="outline" class="flex items-center gap-1 px-2 py-0.5 text-green-600 border-green-200 bg-green-50 dark:bg-green-950/30 dark:border-green-800">
                                <CheckCircle class="size-3" />
                                <span class="text-xs">Verified</span>
                            </Badge>
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Profile Information Form -->
                <Card>
                    <CardHeader>
                        <CardTitle>Profile Information</CardTitle>
                        <CardDescription>Update your account's profile information and email address.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid gap-2">
                                <Label for="name" class="flex items-center gap-2">
                                    <UserIcon class="size-4 text-muted-foreground" />
                                    Name
                                </Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    required
                                    autocomplete="name"
                                    placeholder="Full name"
                                    class="transition-all duration-200 focus-visible:ring-primary/50"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email" class="flex items-center gap-2">
                                    <Mail class="size-4 text-muted-foreground" />
                                    Email address
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    required
                                    autocomplete="username"
                                    placeholder="Email address"
                                    class="transition-all duration-200 focus-visible:ring-primary/50"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-md bg-amber-50 p-4 dark:bg-amber-950/30">
                                <p class="text-sm text-amber-800 dark:text-amber-200">
                                    Your email address is unverified.
                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="font-medium text-amber-600 underline decoration-amber-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:text-amber-400 dark:decoration-amber-700"
                                    >
                                        Click here to resend the verification email.
                                    </Link>
                                </p>

                                <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                                    A new verification link has been sent to your email address.
                                </div>
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="transition-all duration-200"
                                >
                                    <Save class="mr-2 size-4" />
                                    Save changes
                                </Button>

                                <Transition
                                    enter-active-class="transition ease-in-out duration-300"
                                    enter-from-class="opacity-0 transform translate-x-4"
                                    enter-to-class="opacity-100 transform translate-x-0"
                                    leave-active-class="transition ease-in-out duration-300"
                                    leave-from-class="opacity-100 transform translate-x-0"
                                    leave-to-class="opacity-0 transform translate-x-4"
                                >
                                    <div v-show="form.recentlySuccessful" class="flex items-center text-sm text-green-600">
                                        <CheckCircle class="mr-1 size-4" />
                                        <span>Profile updated successfully</span>
                                    </div>
                                </Transition>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Delete Account Section -->
                <div class="mt-4">
                    <DeleteUser />
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
