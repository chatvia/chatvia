<script setup lang="ts">
/**
 * The signed-in chatter's profile dialog: photo upload and removal, display
 * name and an optional password change. Saves through Inertia forms so
 * validation errors land on the right fields.
 */
import ChatPasswordField from '@/components/chat/ChatPasswordField.vue';
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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { getInitials } from '@/composables/useInitials';
import type { SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Loader2, Mail, Trash2, Upload, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const page = usePage<SharedData>();
const currentUser = computed(() => page.props.auth?.user ?? null);

const profileForm = useForm({
    name: '',
    email: '',
    current_password: '',
    password: '',
    password_confirmation: '',
});

const photoForm = useForm({ photo: null as File | null });
const avatarInput = ref<HTMLInputElement | null>(null);
const removingPhoto = ref(false);
const removePhotoDialogOpen = ref(false);

function clearPasswords() {
    profileForm.current_password = '';
    profileForm.password = '';
    profileForm.password_confirmation = '';
}

// Start from the account's current values every time the dialog opens.
watch(open, (isOpen) => {
    if (!isOpen) return;
    profileForm.name = currentUser.value?.name || '';
    profileForm.email = currentUser.value?.email || '';
    clearPasswords();
});

function openAvatarPicker() {
    if (photoForm.processing) return;
    avatarInput.value?.click();
}

function onAvatarSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    photoForm.photo = file;
    photoForm.patch(route('chat.profile.update'), {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            photoForm.reset('photo');
            if (avatarInput.value) avatarInput.value.value = '';
        },
    });
}

function confirmRemoveAvatar() {
    router.delete(route('chat.profile.photo.destroy'), {
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            removingPhoto.value = true;
        },
        onFinish: () => {
            removingPhoto.value = false;
            removePhotoDialogOpen.value = false;
        },
    });
}

function saveProfile() {
    profileForm.patch(route('chat.profile.update'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            clearPasswords();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>{{ t('sidebar.profile_settings_title') }}</DialogTitle>
                <DialogDescription>{{ t('sidebar.profile_settings_description') }}</DialogDescription>
            </DialogHeader>
            <div class="grid gap-6 py-4">
                <div class="flex flex-col items-center gap-2">
                    <button
                        type="button"
                        :disabled="photoForm.processing"
                        :aria-label="currentUser?.avatar ? t('sidebar.change_photo') : t('sidebar.upload_photo')"
                        class="group focus-visible:ring-primary/50 relative cursor-pointer rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-wait"
                        @click="openAvatarPicker"
                    >
                        <Avatar :key="currentUser?.avatar ?? 'no-avatar'" class="border-background size-20 border-2 shadow-sm">
                            <AvatarImage v-if="currentUser?.avatar" :src="currentUser.avatar" :alt="currentUser?.name" />
                            <AvatarFallback class="bg-primary/10 text-primary text-lg font-semibold">
                                {{ getInitials(currentUser?.name || '') }}
                            </AvatarFallback>
                        </Avatar>
                        <div
                            class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-full bg-black/55 text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-visible:opacity-100"
                            :class="{ '!opacity-100': photoForm.processing }"
                        >
                            <Loader2 v-if="photoForm.processing" class="size-5 animate-spin" />
                            <Upload v-else class="size-5" />
                        </div>
                    </button>
                    <input
                        ref="avatarInput"
                        type="file"
                        accept="image/png,image/jpeg,image/webp,image/gif"
                        class="hidden"
                        @change="onAvatarSelected"
                    />
                    <button
                        v-if="currentUser?.avatar"
                        type="button"
                        :disabled="removingPhoto || photoForm.processing"
                        class="text-muted-foreground hover:text-destructive inline-flex cursor-pointer items-center gap-1 text-xs transition-colors disabled:cursor-wait disabled:opacity-60"
                        @click="removePhotoDialogOpen = true"
                    >
                        <Trash2 class="size-3" />
                        {{ t('sidebar.remove_photo') }}
                    </button>
                    <InputError :message="photoForm.errors.photo" class="text-xs" />
                </div>
                <Separator />
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="name" class="text-end">{{ t('sidebar.profile_name') }}</Label>
                    <div class="col-span-3 space-y-1">
                        <div class="relative">
                            <User class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 transform" />
                            <Input id="name" v-model="profileForm.name" class="ps-10" :placeholder="t('sidebar.name_input_placeholder')" />
                        </div>
                        <InputError :message="profileForm.errors.name" class="text-xs" />
                    </div>
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="email" class="text-end">{{ t('sidebar.profile_email') }}</Label>
                    <div class="relative col-span-3">
                        <Mail class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 transform" />
                        <Input
                            id="email"
                            v-model="profileForm.email"
                            type="email"
                            class="bg-muted cursor-not-allowed ps-10"
                            :placeholder="t('sidebar.email_input_placeholder')"
                            readonly
                            disabled
                        />
                    </div>
                </div>
                <Separator />
                <ChatPasswordField
                    id="current-password"
                    v-model="profileForm.current_password"
                    :label="t('sidebar.current_password')"
                    :placeholder="t('sidebar.current_password_placeholder')"
                    :error="profileForm.errors.current_password"
                />
                <ChatPasswordField
                    id="new-password"
                    v-model="profileForm.password"
                    :label="t('sidebar.new_password')"
                    :placeholder="t('sidebar.new_password_placeholder')"
                    :error="profileForm.errors.password"
                />
                <ChatPasswordField
                    id="confirm-password"
                    v-model="profileForm.password_confirmation"
                    :label="t('sidebar.confirm_password')"
                    :placeholder="t('sidebar.confirm_password_placeholder')"
                    :error="profileForm.errors.password_confirmation"
                />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="open = false">{{ t('common.cancel') }}</Button>
                <Button @click="saveProfile" :disabled="profileForm.processing">
                    {{ profileForm.processing ? t('common.saving') : t('sidebar.save_changes') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="removePhotoDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('sidebar.remove_photo_title') }}</AlertDialogTitle>
                <AlertDialogDescription>{{ t('sidebar.remove_photo_description') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="removingPhoto">{{ t('common.cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    :disabled="removingPhoto"
                    @click.prevent="confirmRemoveAvatar"
                >
                    <Loader2 v-if="removingPhoto" class="me-2 size-4 animate-spin" />
                    {{ t('sidebar.remove_photo') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
