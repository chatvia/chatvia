<script setup lang="ts">
import AuthBase from '@/layouts/AuthLayout.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
</script>

<template>
    <AuthBase
        title="No Messages Left"
        description="You've reached your message limit"
    >
        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>


    </AuthBase>
</template>
