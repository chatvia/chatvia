<script lang="ts" setup>
import type { HTMLAttributes } from 'vue'
import { cn } from '@/shared/lib/utils'
import { MoreHorizontal } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <span
    data-slot="breadcrumb-ellipsis"
    role="presentation"
    aria-hidden="true"
    :class="cn('flex size-9 items-center justify-center', props.class)"
  >
    <slot>
      <MoreHorizontal class="size-4" />
    </slot>
    <span class="sr-only">{{ t('shared.accessibility.more') }}</span>
  </span>
</template>
