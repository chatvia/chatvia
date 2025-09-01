<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Auth } from '@/types';
import { Zap } from 'lucide-vue-next';

const page = usePage();
const auth = computed(() => page.props.auth as Auth);
</script>

<template>
    <Link
        v-if="!auth.user.is_paid"
        :href="route('billing.packages')"
        class="hidden lg:flex items-center gap-1.5 font-medium px-4 py-2 text-sm rounded-md transition-all duration-300 ease-out bg-primary text-primary-foreground hover:bg-primary/90 hover:scale-105 hover:shadow-lg transform active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:ring-offset-2 group"
    >
        <Zap class="h-3.5 w-3.5 transition-transform duration-300 group-hover:rotate-12" />
        Upgrade
    </Link>
</template>

<style scoped>
/* no styles */
</style>


