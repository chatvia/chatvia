<script setup lang="ts">
import type { Auth } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Zap } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const auth = computed(() => page.props.auth as Auth);
</script>

<template>
    <Link
        v-if="!auth.user.is_paid"
        :href="route('billing.packages')"
        class="bg-primary text-primary-foreground hover:bg-primary/90 focus:ring-primary/20 group hidden transform items-center gap-1.5 rounded-md px-4 py-2 text-sm font-medium transition-all duration-300 ease-out hover:scale-105 hover:shadow-lg focus:ring-2 focus:ring-offset-2 focus:outline-none active:scale-95 lg:flex"
    >
        <Zap class="h-3.5 w-3.5 transition-transform duration-300 group-hover:rotate-12" />
        Upgrade
    </Link>
</template>

<style scoped>
/* no styles */
</style>
