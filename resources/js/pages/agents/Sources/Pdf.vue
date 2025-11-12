<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import Files from './Files.vue';

interface Props {
    agent: any;
}

defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="PDF">
        <Files :agent="agent" file-type="pdf" />
    </AgentLayout>
</template>
