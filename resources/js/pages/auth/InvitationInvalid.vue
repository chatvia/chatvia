<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Home } from 'lucide-vue-next';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';

interface Props {
    message: string;
    team: {
        name: string;
    };
}

defineProps<Props>();
</script>

<template>
    <AuthBase title="Invitation Unavailable" description="This invitation cannot be accepted">
        <Head title="Invitation Unavailable" />

        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <div class="w-full max-w-lg mx-auto space-y-8">
            <Card class="border-yellow-500/20 bg-yellow-500/5">
                <CardHeader class="text-center pb-3">
                    <div class="mx-auto w-16 h-16 rounded-full bg-yellow-500/10 flex items-center justify-center mb-4">
                        <AlertTriangle class="h-8 w-8 text-yellow-500" />
                    </div>
                    <CardTitle class="text-xl">Cannot Accept Invitation</CardTitle>
                    <CardDescription class="text-base">
                        {{ message }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="text-center space-y-4">
                    <p class="text-muted-foreground">
                        The invitation to join <strong>{{ team.name }}</strong> cannot be accepted at this time.
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Please contact the team owner to resolve this issue.
                    </p>
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

