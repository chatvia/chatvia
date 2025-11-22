import { type HTMLAttributes, type SVGAttributes } from 'vue'

export interface ChartConfig {
  [key: string]: {
    label?: string
    icon?: any
    color?: string
    theme?: Record<string, string>
  }
}

export const THEMES = { light: '', dark: '.dark' } as const

export type ChartContext = {
  config: ChartConfig
}




