<script setup lang="ts">
import { type Component } from 'vue';
import Tooltip from '@/components/ui/tooltip/Tooltip.vue';
import TooltipTrigger from '@/components/ui/tooltip/TooltipTrigger.vue';
import TooltipContent from '@/components/ui/tooltip/TooltipContent.vue';
import TooltipProvider from '@/components/ui/tooltip/TooltipProvider.vue';

defineOptions({
  inheritAttrs: false
});

const props = withDefaults(defineProps<{
  content: string | Component;
  side?: 'top' | 'right' | 'bottom' | 'left';
  align?: 'start' | 'center' | 'end';
  sideOffset?: number;
  alignOffset?: number;
  hideWhen?: boolean;
  delayDuration?: number;
}>(), {
  side: 'top',
  align: 'center',
  sideOffset: 4,
  alignOffset: 0,
  hideWhen: false,
  delayDuration: 0
});
</script>

<template>
  <TooltipProvider :delay-duration="delayDuration">
    <Tooltip>
      <TooltipTrigger as-child>
        <slot />
      </TooltipTrigger>
      <TooltipContent
        :side="side"
        :align="align"
        :side-offset="sideOffset"
        :align-offset="alignOffset"
        :hidden="hideWhen"
      >
        <template v-if="typeof content === 'string'">
          {{ content }}
        </template>
        <component :is="content" v-else />
      </TooltipContent>
    </Tooltip>
  </TooltipProvider>
</template>
