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
import AccountLayout from '@/layouts/account/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';
import { Building2, Check, CheckCircle, Copy, Fingerprint, Loader2, Mail, Save, Trash2, Upload, User as UserIcon } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface DateFormatOption {
    value: DateFormatKey;
    label: string;
    example: string;
}

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    dateFormats: DateFormatOption[];
    // Name of the workspace that manages this account when the user is a
    // teammate (employee); null for standalone workspace owners.
    managedBy?: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('account.profile.title'),
        href: '/my/account/profile',
    },
];

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);

const externalIdCopied = ref(false);
const copyExternalId = async () => {
    await navigator.clipboard.writeText(user.value.external_id);
    externalIdCopied.value = true;
    setTimeout(() => (externalIdCopied.value = false), 2000);
};

const form = useForm({
    name: user.value.name,
    email: user.value.email,
});

const submit = () => {
    form.patch(route('account.profile.update'), {
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

    photoForm.patch(route('account.profile.update'), {
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
    router.delete(route('account.profile.photo.destroy'), {
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
    dateForm.patch(route('account.preferences.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('account.profile.title')" />

        <AccountLayout>
            <div class="flex flex-col space-y-8">
                <!-- Profile Header with Avatar -->
                <div class="flex flex-col items-center space-y-4 sm:flex-row sm:gap-6 sm:space-y-0">
                    <button
                        type="button"
                        @click="openFilePicker"
                        :disabled="photoForm.processing"
                        class="group focus-visible:ring-primary/50 relative cursor-pointer rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-wait"
                        :aria-label="user.avatar ? t('account.profile.change_photo') : t('account.profile.upload_photo')"
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
                                <span class="mt-1 text-[10px] font-medium tracking-wide uppercase">{{ t('common.upload') }}</span>
                            </template>
                        </div>
                    </button>
                    <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="onPhotoSelected" />
                    <div class="space-y-1 text-center sm:text-start">
                        <h1 class="text-2xl font-bold tracking-tight">{{ user.name }}</h1>
                        <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                            <p class="text-muted-foreground flex items-center text-sm">
                                <Mail class="me-1 size-3.5" />
                                {{ user.email }}
                            </p>
                        </div>
                        <div v-if="managedBy" class="flex items-center justify-center sm:justify-start">
                            <span
                                class="bg-muted text-muted-foreground inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                            >
                                <Building2 class="size-3.5 shrink-0" />
                                {{ t('account.profile.managed_by', { name: managedBy }) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-center sm:justify-start">
                            <button
                                type="button"
                                @click="copyExternalId"
                                class="text-muted-foreground hover:text-foreground group inline-flex min-w-0 cursor-pointer items-center gap-1.5 text-xs transition-colors"
                                :title="externalIdCopied ? t('account.profile.copied') : t('account.profile.copy_account_id')"
                            >
                                <Fingerprint class="size-3.5 shrink-0" />
                                <code class="truncate font-mono">{{ user.external_id }}</code>
                                <Check v-if="externalIdCopied" class="size-3.5 shrink-0 text-green-600" />
                                <Copy v-else class="size-3.5 shrink-0 opacity-60 transition-opacity group-hover:opacity-100" />
                            </button>
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
                            {{ t('account.profile.remove_photo') }}
                        </button>
                    </div>
                </div>

                <AlertDialog v-model:open="removeOpen">
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{ t('account.profile.remove_photo_title') }}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {{ t('account.profile.remove_photo_description') }}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel :disabled="removing">{{ t('common.cancel') }}</AlertDialogCancel>
                            <AlertDialogAction
                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                :disabled="removing"
                                @click="confirmRemovePhoto"
                            >
                                <Loader2 v-if="removing" class="me-2 size-4 animate-spin" />
                                {{ t('common.remove') }}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <Separator />

                <!-- Profile Information Form -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('account.profile.info_title') }}</CardTitle>
                        <CardDescription>{{ t('account.profile.info_description') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid gap-2">
                                <Label for="name">{{ t('account.profile.name') }}</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                        <UserIcon class="size-4" />
                                    </div>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        required
                                        autocomplete="name"
                                        :placeholder="t('account.profile.name_placeholder')"
                                        class="focus-visible:ring-primary/50 ps-9 transition-all duration-200"
                                    />
                                </div>
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">{{ t('account.profile.email') }}</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                        <Mail class="size-4" />
                                    </div>
                                    <Input
                                        id="email"
                                        type="email"
                                        v-model="form.email"
                                        required
                                        autocomplete="username"
                                        :placeholder="t('account.profile.email_placeholder')"
                                        class="focus-visible:ring-primary/50 ps-9 transition-all duration-200"
                                    />
                                </div>
                                <InputError :message="form.errors.email" />
                            </div>

                            <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-md bg-amber-50 p-4 dark:bg-amber-950/30">
                                <p class="text-sm text-amber-800 dark:text-amber-200">
                                    {{ t('account.profile.email_unverified') }}
                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="font-medium text-amber-600 underline decoration-amber-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:text-amber-400 dark:decoration-amber-700"
                                    >
                                        {{ t('account.profile.resend_verification') }}
                                    </Link>
                                </p>

                                <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                                    {{ t('account.profile.verification_sent') }}
                                </div>
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <Button type="submit" :disabled="form.processing" class="transition-all duration-200">
                                    <Save class="me-2 size-4" />
                                    {{ t('common.save') }}
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
                                        <CheckCircle class="me-1 size-4" />
                                        <span>{{ t('account.profile.updated') }}</span>
                                    </div>
                                </Transition>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Date & time preferences -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('account.profile.datetime_title') }}</CardTitle>
                        <CardDescription>{{ t('account.profile.datetime_description') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitDateFormat" class="space-y-6">
                            <div class="grid gap-2">
                                <Label for="date_format">{{ t('account.profile.date_format') }}</Label>
                                <Select v-model="dateForm.date_format">
                                    <SelectTrigger id="date_format" class="w-full sm:w-80">
                                        <SelectValue :placeholder="t('account.profile.date_format_placeholder')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in props.dateFormats" :key="option.value" :value="option.value">
                                            <span class="font-medium">{{ option.example }}</span>
                                            <span class="text-muted-foreground ms-2">{{ option.label }}</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="dateForm.errors.date_format" />
                            </div>

                            <div class="bg-muted/50 rounded-md px-4 py-3 text-sm">
                                <span class="text-muted-foreground">{{ t('common.preview') }}:</span>
                                <span class="ms-2 font-medium">{{ datePreview }}</span>
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <Button type="submit" :disabled="dateForm.processing">
                                    <Save class="me-2 size-4" />
                                    {{ t('common.save') }}
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
                                        <CheckCircle class="me-1 size-4" />
                                        <span>{{ t('common.saved') }}</span>
                                    </div>
                                </Transition>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AccountLayout>
    </AppLayout>
</template>
