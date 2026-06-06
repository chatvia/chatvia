<script setup lang="ts">
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
</script>

<template>
    <AuthBase title="Assistant Unavailable" description="This assistant has reached its usage limit. Please check back soon.">
        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>
    </AuthBase>
</template>
