<script setup lang="ts">
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { AlertTriangle, Mail } from 'lucide-vue-next';
</script>

<template>
    <AuthBase title="Agent Disabled" description="This agent is currently unavailable">
        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="mx-auto w-full max-w-lg space-y-8">
            <!-- Alert Section -->
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-950/50">
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <AlertTriangle class="h-8 w-8 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-amber-800 dark:text-amber-200">Agent Disabled</h3>
                        <p class="mt-2 text-base text-amber-700 dark:text-amber-300">
                            This agent has been disabled. Please contact the administrator for assistance.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="space-y-4 text-center">
                <p class="text-muted-foreground">
                    If you believe this is an error or need immediate assistance, please reach out to your administrator.
                </p>
            </div>

            <!-- Contact Button -->
            <div class="space-y-4">
                <Button type="button" variant="outline" class="h-12 w-full text-base font-medium">
                    <Mail class="mr-2 h-5 w-5" />
                    Contact Administrator
                </Button>
            </div>

            <!-- Back to Dashboard -->
            <div class="pt-4 text-center">
                <p class="text-muted-foreground text-base">
                    <a href="/dashboard" class="text-primary hover:text-primary/80 font-semibold underline"> Return to Dashboard </a>
                </p>
            </div>
        </div>
    </AuthBase>
</template>
