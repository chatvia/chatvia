<script setup lang="ts">
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Clock, Home } from 'lucide-vue-next';

interface Props {
    message: string;
}

defineProps<Props>();
</script>

<template>
    <AuthBase title="Invitation Expired" description="This invitation is no longer valid">
        <Head title="Invitation Expired" />

        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <div class="mx-auto w-full max-w-lg space-y-8">
            <Card class="border-destructive/20 bg-destructive/5">
                <CardHeader class="pb-3 text-center">
                    <div class="bg-destructive/10 mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                        <Clock class="text-destructive h-8 w-8" />
                    </div>
                    <CardTitle class="text-xl">Invitation Expired</CardTitle>
                    <CardDescription class="text-base">
                        {{ message }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 text-center">
                    <p class="text-muted-foreground">Please contact the team owner to request a new invitation.</p>
                    <Link :href="route('login')">
                        <Button variant="outline" class="gap-2">
                            <Home class="h-4 w-4" />
                            Go to Login
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </AuthBase>
</template>
