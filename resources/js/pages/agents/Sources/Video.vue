<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import Files from './Files.vue';

interface Props {
    agent: any;
}

defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="Video">
        <Files :agent="agent" file-type="video" />
    </AgentLayout>
</template>
