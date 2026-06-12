<script setup lang="ts">
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Home } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    message: string;
    team: {
        name: string;
    };
}

defineProps<Props>();
</script>

<template>
    <AuthBase :title="t('auth.invitation_invalid.title')" :description="t('auth.invitation_invalid.description')">
        <Head :title="t('auth.invitation_invalid.head')" />

        <div class="absolute top-4 end-4">
            <ThemeSwitcher />
        </div>

        <div class="mx-auto w-full max-w-lg space-y-8">
            <Card class="border-yellow-500/20 bg-yellow-500/5">
                <CardHeader class="pb-3 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-yellow-500/10">
                        <AlertTriangle class="h-8 w-8 text-yellow-500" />
                    </div>
                    <CardTitle class="text-xl">{{ t('auth.invitation_invalid.card_title') }}</CardTitle>
                    <CardDescription class="text-base">
                        {{ message }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 text-center">
                    <p class="text-muted-foreground">
                        {{ t('auth.invitation_invalid.cannot_accept_prefix') }} <strong>{{ team.name }}</strong> {{ t('auth.invitation_invalid.cannot_accept_suffix') }}
                    </p>
                    <p class="text-muted-foreground text-sm">{{ t('auth.invitation_invalid.contact_owner') }}</p>
                    <Link :href="route('login')">
                        <Button variant="outline" class="gap-2">
                            <Home class="h-4 w-4" />
                            {{ t('auth.invitation_invalid.go_to_login') }}
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </AuthBase>
</template>
