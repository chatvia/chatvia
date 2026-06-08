<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { formatDateWith, type DateFormatKey } from '@/composables/useDateFormat';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';
import { CheckCircle, Loader2, Mail, Save, Trash2, Upload, User as UserIcon } from 'lucide-vue-next';

interface DateFormatOption {
    value: DateFormatKey;
    label: string;
    example: string;
}

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    dateFormats: DateFormatOption[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);

const form = useForm({
    name: user.value.name,
    email: user.value.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};

const fileInput = ref<HTMLInputElement | null>(null);

const photoForm = useForm({
    name: user.value.name,
    email: user.value.email,
    photo: null as File | null,
});

const openFilePicker = () => {
    if (photoForm.processing) return;
    fileInput.value?.click();
};

const onPhotoSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) return;

    photoForm.name = user.value.name;
    photoForm.email = user.value.email;
    photoForm.photo = file;

    photoForm.patch(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            photoForm.reset('photo');
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};

const removeOpen = ref(false);
const removing = ref(false);

const confirmRemovePhoto = () => {
    router.delete(route('profile.photo.destroy'), {
        preserveScroll: true,
        onStart: () => {
            removing.value = true;
        },
        onFinish: () => {
            removing.value = false;
            removeOpen.value = false;
        },
    });
};

const dateForm = useForm({
    date_format: (user.value.date_format as DateFormatKey) ?? 'iso',
});

// Live preview of today's date in the currently selected format.
const datePreview = computed(() => formatDateWith(dateForm.date_format, new Date()));

const submitDateFormat = () => {
    dateForm.patch(route('settings.preferences.update'), {
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
                <div class="flex flex-col items-center space-y-4 sm:flex-row sm:space-y-0 sm:space-x-6">
                    <button
                        type="button"
                        @click="openFilePicker"
                        :disabled="photoForm.processing"
                        class="group focus-visible:ring-primary/50 relative cursor-pointer rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-wait"
                        :aria-label="user.avatar ? 'Change profile photo' : 'Upload profile photo'"
                    >
                        <Avatar :key="user.avatar ?? 'no-avatar'" class="border-background size-24 border-4 shadow-md">
                            <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                            <AvatarFallback class="bg-primary/10 text-primary text-xl font-semibold">
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div
                            class="pointer-events-none absolute inset-1 flex flex-col items-center justify-center rounded-full bg-black/55 text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-visible:opacity-100"
                            :class="{ '!opacity-100': photoForm.processing }"
                        >
                            <Loader2 v-if="photoForm.processing" class="size-6 animate-spin" />
                            <template v-else>
                                <Upload class="size-6" />
                                <span class="mt-1 text-[10px] font-medium tracking-wide uppercase">Upload</span>
                            </template>
                        </div>
                    </button>
                    <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="onPhotoSelected" />
                    <div class="space-y-1 text-center sm:text-left">
                        <h1 class="text-2xl font-bold tracking-tight">{{ user.name }}</h1>
                        <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                            <p class="text-muted-foreground flex items-center text-sm">
                                <Mail class="mr-1 size-3.5" />
                                {{ user.email }}
                            </p>
                        </div>
                        <InputError :message="photoForm.errors.photo" />
                        <button
                            v-if="user.avatar"
                            type="button"
                            @click="removeOpen = true"
                            :disabled="removing || photoForm.processing"
                            class="text-muted-foreground hover:text-destructive inline-flex cursor-pointer items-center gap-1 text-xs transition-colors disabled:cursor-wait disabled:opacity-60"
                        >
                            <Trash2 class="size-3" />
                            Remove photo
                        </button>
                    </div>
                </div>

                <AlertDialog v-model:open="removeOpen">
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Remove profile photo</AlertDialogTitle>
                            <AlertDialogDescription>
                                Your avatar will revert to your initials. You can upload a new photo at any time.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel :disabled="removing">Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                :disabled="removing"
                                @click="confirmRemovePhoto"
                            >
                                <Loader2 v-if="removing" class="mr-2 size-4 animate-spin" />
                                Remove
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

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
                                <Label for="name">Name</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <UserIcon class="size-4" />
                                    </div>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        required
                                        autocomplete="name"
                                        placeholder="Full name"
                                        class="focus-visible:ring-primary/50 pl-9 transition-all duration-200"
                                    />
                                </div>
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">Email address</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <Mail class="size-4" />
                                    </div>
                                    <Input
                                        id="email"
                                        type="email"
                                        v-model="form.email"
                                        required
                                        autocomplete="username"
                                        placeholder="Email address"
                                        class="focus-visible:ring-primary/50 pl-9 transition-all duration-200"
                                    />
                                </div>
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
                                <Button type="submit" :disabled="form.processing" class="transition-all duration-200">
                                    <Save class="mr-2 size-4" />
                                    Save
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

                <!-- Date & time preferences -->
                <Card>
                    <CardHeader>
                        <CardTitle>Date &amp; time</CardTitle>
                        <CardDescription>Choose how dates are displayed across your dashboard.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitDateFormat" class="space-y-6">
                            <div class="grid gap-2">
                                <Label for="date_format">Date format</Label>
                                <Select v-model="dateForm.date_format">
                                    <SelectTrigger id="date_format" class="w-full sm:w-80">
                                        <SelectValue placeholder="Select a date format" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in props.dateFormats" :key="option.value" :value="option.value">
                                            <span class="font-medium">{{ option.example }}</span>
                                            <span class="text-muted-foreground ml-2">{{ option.label }}</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="dateForm.errors.date_format" />
                            </div>

                            <div class="bg-muted/50 rounded-md px-4 py-3 text-sm">
                                <span class="text-muted-foreground">Preview:</span>
                                <span class="ml-2 font-medium">{{ datePreview }}</span>
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <Button type="submit" :disabled="dateForm.processing">
                                    <Save class="mr-2 size-4" />
                                    Save
                                </Button>

                                <Transition
                                    enter-active-class="transition ease-in-out duration-300"
                                    enter-from-class="opacity-0 transform translate-x-4"
                                    enter-to-class="opacity-100 transform translate-x-0"
                                    leave-active-class="transition ease-in-out duration-300"
                                    leave-from-class="opacity-100 transform translate-x-0"
                                    leave-to-class="opacity-0 transform translate-x-4"
                                >
                                    <div v-show="dateForm.recentlySuccessful" class="flex items-center text-sm text-green-600">
                                        <CheckCircle class="mr-1 size-4" />
                                        <span>Saved</span>
                                    </div>
                                </Transition>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
