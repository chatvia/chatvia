<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Lock, Zap } from 'lucide-vue-next';

interface Props {
    description: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex items-center justify-center min-h-[calc(100vh-20rem)]">
        <div class="max-w-md w-full text-center space-y-8">
            <!-- Lock Icon -->
            <div class="mx-auto w-20 h-20 rounded-full bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center">
                <Lock class="h-10 w-10 text-primary" />
            </div>

            <!-- Description -->
            <div class="space-y-3">
                <p class="text-lg text-muted-foreground">
                    {{ description }}
                </p>
            </div>

            <!-- Upgrade Button -->
            <Button
                as-child
                size="lg"
                class="gap-2 shadow-lg hover:shadow-xl transition-all duration-300"
            >
                <Link :href="route('billing.packages')">
                    <Zap class="h-5 w-5" />
                    Upgrade Now
                </Link>
            </Button>
        </div>
    </div>
</template>

