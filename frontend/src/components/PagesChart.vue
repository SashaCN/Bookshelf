<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DailyPages } from '@/types/api'
import { bestDay, layoutBars, totalPages } from '@/utils/chart'
import { formatDay, formatNumber, formatShortDay } from '@/utils/format'

const WIDTH = 300
const HEIGHT = 100
/** A day without reading is still marked, so the reader can tell it from a missing day. */
const EMPTY_DAY_HEIGHT = 1.5

const props = defineProps<{ days: DailyPages[] }>()
const { t } = useI18n()

const bars = computed(() => layoutBars(props.days, WIDTH, HEIGHT))
const total = computed(() => totalPages(props.days))
const average = computed(() => (props.days.length ? Math.round((total.value / props.days.length) * 10) / 10 : 0))
const best = computed(() => bestDay(props.days))
const first = computed(() => props.days[0])
const last = computed(() => props.days[props.days.length - 1])
</script>

<template>
  <figure class="flex flex-col gap-2">
    <svg
      :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
      preserveAspectRatio="none"
      role="img"
      :aria-label="t('stats.chart.label', { total: formatNumber(total), n: days.length }, days.length)"
      class="h-28 w-full"
    >
      <rect
        v-for="(bar, index) in bars"
        :key="bar.date"
        :x="bar.x"
        :y="bar.pages ? bar.y : HEIGHT - EMPTY_DAY_HEIGHT"
        :width="bar.width"
        :height="bar.pages ? bar.height : EMPTY_DAY_HEIGHT"
        rx="1"
        :class="bar.pages === 0 ? 'fill-sunken' : index === bars.length - 1 ? 'fill-accent' : 'fill-accent/55'"
      >
        <title>{{ t('stats.chart.day', { date: formatDay(bar.date), pages: formatNumber(bar.pages) }) }}</title>
      </rect>
    </svg>

    <div v-if="first && last" class="flex justify-between text-xs text-ink-muted">
      <span>{{ formatShortDay(first.date) }}</span>
      <span>{{ formatShortDay(last.date) }}</span>
    </div>

    <figcaption class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
      <span class="text-ink-muted">{{ t('stats.chart.total') }}</span>
      <span class="text-right font-semibold tabular-nums">{{ t('stats.pages', { n: formatNumber(total) }) }}</span>
      <span class="text-ink-muted">{{ t('stats.chart.average') }}</span>
      <span class="text-right font-semibold tabular-nums">{{ t('stats.perDay', { n: formatNumber(average) }) }}</span>
      <template v-if="best">
        <span class="text-ink-muted">{{ t('stats.chart.best') }}</span>
        <span class="text-right font-semibold tabular-nums">
          {{ formatDay(best.date) }}, {{ t('stats.pages', { n: formatNumber(best.pages) }) }}
        </span>
      </template>
    </figcaption>
  </figure>
</template>
