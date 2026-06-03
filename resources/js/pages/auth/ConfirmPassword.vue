<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, ShieldCheck } from 'lucide-vue-next';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm.store'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <AuthLayout title="Confirm your password" description="This is a secure area of the application. Please confirm your password before continuing.">
        <Head title="Confirm password" />

        <form @submit.prevent="submit" class="mx-auto w-full max-w-lg space-y-6">
            <div class="space-y-2">
                <Label for="password" class="text-base font-medium">Password</Label>
                <div class="relative">
                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <Lock class="h-5 w-5" />
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        autofocus
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="Enter your password"
                        class="h-12 pl-10 text-base"
                    />
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <Button type="submit" class="h-12 w-full text-base font-semibold" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                <ShieldCheck v-else class="mr-2 h-5 w-5" />
                <span v-if="!form.processing">Confirm password</span>
                <span v-else>Confirming…</span>
            </Button>
        </form>
    </AuthLayout>
</template>
