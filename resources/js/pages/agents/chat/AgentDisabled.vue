<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { AlertTriangle, Mail } from 'lucide-vue-next';
import AuthBase from '@/layouts/AuthLayout.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
</script>

<template>
    <AuthBase 
        title="Agent Disabled" 
        description="This agent is currently unavailable"
    >
        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>
        
        <!-- Clean, spacious container -->
        <div class="w-full max-w-lg mx-auto space-y-8">
            <!-- Alert Section -->
            <div class="p-6 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 rounded-lg">
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <AlertTriangle class="h-8 w-8 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-amber-800 dark:text-amber-200">
                            Agent Disabled
                        </h3>
                        <p class="mt-2 text-base text-amber-700 dark:text-amber-300">
                            This agent has been disabled. Please contact the administrator for assistance.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="text-center space-y-4">
                <p class="text-muted-foreground">
                    If you believe this is an error or need immediate assistance, please reach out to your administrator.
                </p>
            </div>

            <!-- Contact Button -->
            <div class="space-y-4">
                <Button
                    type="button"
                    variant="outline"
                    class="w-full h-12 text-base font-medium"
                >
                    <Mail class="h-5 w-5 mr-2" />
                    Contact Administrator
                </Button>
            </div>

            <!-- Back to Dashboard -->
            <div class="text-center pt-4">
                <p class="text-base text-muted-foreground">
                    <a 
                        href="/dashboard" 
                        class="font-semibold text-primary hover:text-primary/80 underline"
                    >
                        Return to Dashboard
                    </a>
                </p>
            </div>
        </div>
    </AuthBase>
</template> 