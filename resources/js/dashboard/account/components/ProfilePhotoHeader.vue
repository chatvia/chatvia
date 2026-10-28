<script setup lang="ts">
// Top of the profile page: the avatar (click to upload a new photo), name,
// email, managing workspace, a copyable account ID, and "remove photo" with
// its confirmation. The photo upload re-sends name + email because the
// profile endpoint validates them on every update.
import { router, useForm } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Building2, Check, Copy, Fingerprint, Loader2, Mail, Trash2, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import InputError from '@/shared/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/shared/ui/alert-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/shared/ui/avatar';
import { getInitials } from '@/shared/composables/useInitials';
import { update as updateProfile } from '@/routes/account/profile';
import { destroy as destroyProfilePhoto } from '@/routes/account/profile/photo';
import { type User } from '@/shared/types';

const props = defineProps<{
    user: User;
    // Name of the workspace that manages this account when the user is a
    // teammate (employee); null for standalone workspace owners.
    managedBy?: string | null;
}>();

const { t } = useI18n();
const { copy } = useClipboard({ legacy: true });

const externalIdCopied = ref(false);
const copyExternalId = async () => {
    await copy(props.user.external_id);
    externalIdCopied.value = true;
    setTimeout(() => (externalIdCopied.value = false), 2000);
};

const fileInput = ref<HTMLInputElement | null>(null);

const photoForm = useForm({
    name: props.user.name,
    email: props.user.email,
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

    photoForm.name = props.user.name;
    photoForm.email = props.user.email;
    photoForm.photo = file;

    photoForm.patch(updateProfile.url(), {
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
    router.delete(destroyProfilePhoto.url(), {
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
</script>

<template>
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
                <span class="bg-muted text-muted-foreground inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium">
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
</template>
