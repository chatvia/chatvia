<script setup lang="ts">
// Account → Profile: the photo header, the name/email form and the date-format
// preference. The header and the date-format card are self-contained.
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import AccountLayout from '@/layouts/account/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';
import { CheckCircle, Mail, Save, User as UserIcon } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import DateFormatCard, { type DateFormatOption } from './components/DateFormatCard.vue';
import ProfilePhotoHeader from './components/ProfilePhotoHeader.vue';

const { t } = useI18n();

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

const form = useForm({
    name: user.value.name,
    email: user.value.email,
});

const submit = () => {
    form.patch(route('account.profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('account.profile.title')" />

        <AccountLayout>
            <div class="flex flex-col space-y-8">
                <ProfilePhotoHeader :user="user" :managed-by="managedBy" />

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

                <DateFormatCard :user="user" :date-formats="props.dateFormats" />
            </div>
        </AccountLayout>
    </AppLayout>
</template>
