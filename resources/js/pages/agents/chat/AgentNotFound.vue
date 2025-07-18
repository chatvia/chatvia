<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Search, Mail } from 'lucide-vue-next';
import AuthBase from '@/layouts/AuthLayout.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
</script>

<template>
    <AuthBase
        title="Agent Not Found"
        description="The agent you're looking for doesn't exist"
    >
        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="w-full max-w-lg mx-auto space-y-8">
            <!-- Alert Section -->
            <div class="p-6 bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800 rounded-lg">
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <Search class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">
                            Agent Not Found
                        </h3>
                        <p class="mt-2 text-base text-blue-700 dark:text-blue-300">
                            The agent you're trying to access doesn't exist or may have been removed.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="text-center space-y-4">
                <p class="text-muted-foreground">
                    Please check the URL or contact your administrator if you believe this agent should be available.
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

        </div>
    </AuthBase>
</template>
