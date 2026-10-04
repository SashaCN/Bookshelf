<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { statsApi } from '@/api/stats'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import GoalCard from '@/components/GoalCard.vue'
import PageHeader from '@/components/PageHeader.vue'
import PagesChart from '@/components/PagesChart.vue'
import StatTile from '@/components/StatTile.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import type { DailyPages, GoalProgress, StatsSummary } from '@/types/api'
import { formatDay, formatNumber } from '@/utils/format'

const RANGES = [7, 30, 90] as const

const { t } = useI18n()
const errorMessage = useApiErrorMessage()

const summary = ref<StatsSummary | null>(null)
const goal = ref<GoalProgress | null>(null)
const days = ref<DailyPages[]>([])
const range = ref<(typeof RANGES)[number]>(30)

const failed = ref(false)
const chartError = ref('')
const goalError = ref('')
const goalBusy = ref(false)
// Answers to older range requests must not overwrite the newest one.
let rangeRequest = 0

async function load() {
  failed.value = false

  try {
    summary.value = await statsApi.summary()

    // The year comes from the reader's own calendar (the server's "today"), not from the device clock.
    const year = Number(summary.value.today.date.slice(0, 4))
    const [loadedGoal] = await Promise.all([statsApi.goal(year), loadDays(range.value)])
    goal.value = loadedGoal
  } catch {
    failed.value = true
  }
}

async function loadDays(count: number) {
  const request = ++rangeRequest
  chartError.value = ''

  try {
    const result = await statsApi.daily(count)
    if (request === rangeRequest) days.value = result.data
  } catch (e) {
    if (request === rangeRequest) chartError.value = errorMessage(e)
  }
}

function selectRange(count: (typeof RANGES)[number]) {
  range.value = count
  return loadDays(count)
}

async function saveGoal(targetBooks: number) {
  if (!goal.value) return

  goalBusy.value = true
  goalError.value = ''

  try {
    goal.value = await statsApi.setGoal(goal.value.year, targetBooks)
  } catch (e) {
    goalError.value = errorMessage(e)
  } finally {
    goalBusy.value = false
  }
}

async function removeGoal() {
  if (!goal.value) return

  goalBusy.value = true
  goalError.value = ''

  try {
    await statsApi.removeGoal(goal.value.year)
    goal.value = await statsApi.goal(goal.value.year)
  } catch (e) {
    goalError.value = errorMessage(e)
  } finally {
    goalBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <PageHeader :title="$t('stats.title')" />

  <ErrorNotice v-if="failed" :message="$t('apiErrors.generic')" retryable @retry="load" />

  <p v-else-if="!summary || !goal" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <div v-else class="flex flex-col gap-4">
    <GoalCard :goal="goal" :busy="goalBusy" :error="goalError" @save="saveGoal" @remove="removeGoal" />

    <div class="grid grid-cols-2 gap-3">
      <StatTile
        :label="t('stats.tiles.streak')"
        :value="t('stats.days', { n: summary.streak.current }, summary.streak.current)"
        :hint="
          summary.streak.current > 0 && !summary.streak.read_today
            ? t('stats.tiles.streakKeep')
            : t('stats.tiles.streakLongest', { n: summary.streak.longest }, summary.streak.longest)
        "
      />
      <StatTile
        :label="t('stats.tiles.pace')"
        :value="formatNumber(summary.pace)"
        :unit="t('stats.perDayUnit')"
        :hint="t('stats.tiles.paceHint')"
      />
      <StatTile
        :label="t('stats.tiles.month')"
        :value="t('stats.pages', { n: formatNumber(summary.month.pages) })"
        :hint="t('stats.books', { n: summary.month.books_finished }, summary.month.books_finished)"
      />
      <StatTile
        :label="t('stats.tiles.year')"
        :value="t('stats.pages', { n: formatNumber(summary.year.pages) })"
        :hint="t('stats.books', { n: summary.year.books_finished }, summary.year.books_finished)"
      />
    </div>

    <section class="flex flex-col gap-3 rounded-2xl border border-line bg-surface p-4">
      <div class="flex items-center justify-between gap-3">
        <h2 class="font-display text-xl font-semibold">{{ $t('stats.chart.title') }}</h2>
        <div class="flex gap-1" role="group" :aria-label="$t('stats.chart.range')">
          <button
            v-for="count in RANGES"
            :key="count"
            type="button"
            :aria-pressed="range === count"
            class="min-h-10 min-w-10 rounded-full border px-3 text-sm font-medium"
            :class="range === count ? 'border-accent bg-accent text-on-accent' : 'border-line bg-surface text-ink-muted'"
            @click="selectRange(count)"
          >
            {{ count }}
          </button>
        </div>
      </div>

      <ErrorNotice v-if="chartError" :message="chartError" retryable @retry="loadDays(range)" />
      <PagesChart v-else :days="days" />
    </section>

    <section class="flex flex-col gap-2">
      <h2 class="font-display text-xl font-semibold">{{ $t('stats.forecast.title') }}</h2>

      <EmptyState
        v-if="!summary.forecasts.length"
        :title="$t('stats.forecast.emptyTitle')"
        :text="$t('stats.forecast.emptyText')"
      />

      <ul v-else class="flex flex-col divide-y divide-line rounded-2xl border border-line bg-surface">
        <li v-for="forecast in summary.forecasts" :key="forecast.user_book_id">
          <RouterLink
            :to="{ name: 'book', params: { id: forecast.user_book_id } }"
            class="flex flex-col gap-0.5 px-4 py-3 active:bg-sunken"
          >
            <span class="truncate font-medium">{{ forecast.title }}</span>
            <span v-if="forecast.total_pages > forecast.current_page" class="text-sm text-ink-muted">
              {{ t('stats.forecast.left', { n: formatNumber(forecast.total_pages - forecast.current_page) }) }}
              <template v-if="forecast.pages_per_day"> · {{ t('stats.perDay', { n: formatNumber(forecast.pages_per_day) }) }}</template>
            </span>
            <span class="text-sm font-semibold tabular-nums" :class="forecast.finish_on ? 'text-accent' : 'text-ink-muted'">
              <template v-if="forecast.days_left === 0">{{ t('stats.forecast.atEnd') }}</template>
              <template v-else-if="forecast.finish_on === null || forecast.days_left === null">{{ t('stats.forecast.unknown') }}</template>
              <template v-else>
                {{ t('stats.forecast.finish', { date: formatDay(forecast.finish_on), n: forecast.days_left }, forecast.days_left) }}
              </template>
            </span>
          </RouterLink>
        </li>
      </ul>
    </section>
  </div>
</template>
