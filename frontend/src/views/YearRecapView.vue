<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { statsApi } from '@/api/stats'
import { ApiError } from '@/api/http'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import RatingStars from '@/components/RatingStars.vue'
import StatTile from '@/components/StatTile.vue'
import type { YearRecapResponse } from '@/types/stats'
import { formatDay, formatNumber, monthName } from '@/utils/format'

const props = defineProps<{ year: number }>()

const { t } = useI18n()

const recap = ref<YearRecapResponse | null>(null)
const failed = ref(false)
const notFound = ref(false)
// Answers to a year the reader has already left must not overwrite the current one.
let request = 0

async function load() {
  const current = ++request
  failed.value = false
  notFound.value = false
  recap.value = null

  try {
    const result = await statsApi.year(props.year)
    if (current === request) recap.value = result
  } catch (e) {
    if (current !== request) return
    if (e instanceof ApiError && e.status === 404) notFound.value = true
    else failed.value = true
  }
}

watch(() => props.year, load, { immediate: true })

const data = computed(() => recap.value?.data ?? null)
const isEmpty = computed(
  () => !!data.value && data.value.books_finished === 0 && data.value.pages_read === 0 && data.value.quotes_added === 0,
)
const hasPrevious = computed(() => !recap.value || props.year > recap.value.meta.first_year)
const hasNext = computed(() => !recap.value || props.year < recap.value.meta.current_year)

function authors(list: string[]) {
  return list.join(', ')
}
</script>

<template>
  <PageHeader :title="t('recap.title', { year })" :back-to="{ name: 'stats' }" />

  <ErrorNotice v-if="failed" :message="$t('apiErrors.generic')" retryable @retry="load" />

  <EmptyState v-else-if="notFound" :title="t('recap.emptyTitle', { year })" :text="t('recap.notFound')" />

  <p v-else-if="!data" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <div v-else class="flex flex-col gap-5">
    <EmptyState v-if="isEmpty" :title="t('recap.emptyTitle', { year })" :text="t('recap.emptyText')" />

    <template v-else>
      <section class="flex flex-col gap-2" aria-labelledby="recap-numbers">
        <h2 id="recap-numbers" class="font-display text-xl font-semibold">{{ $t('recap.numbers') }}</h2>
        <div class="grid grid-cols-2 gap-3">
          <StatTile :label="t('recap.books')" :value="formatNumber(data.books_finished)" />
          <StatTile :label="t('recap.pagesRead')" :value="formatNumber(data.pages_read)" />
          <StatTile
            :label="t('recap.daysReading')"
            :value="formatNumber(data.days_with_reading)"
            :hint="t('recap.daysReadingHint', { n: data.longest_streak_in_year }, data.longest_streak_in_year)"
          />
          <StatTile :label="t('recap.quotes')" :value="formatNumber(data.quotes_added)" />
          <StatTile :label="t('recap.reviews')" :value="formatNumber(data.reviews_written)" />
          <StatTile v-if="data.average_rating !== null" :label="t('recap.averageRating')" :value="formatNumber(data.average_rating)" unit="/ 5" />
        </div>
      </section>

      <section v-if="data.goal" class="flex flex-col gap-2" aria-labelledby="recap-goal">
        <h2 id="recap-goal" class="font-display text-xl font-semibold">{{ $t('recap.goal') }}</h2>
        <p class="rounded-2xl border border-line bg-surface p-4 font-medium" :class="data.goal.achieved >= data.goal.target ? 'text-accent' : ''">
          {{ t(data.goal.achieved >= data.goal.target ? 'recap.goalDone' : 'recap.goalMissed', data.goal) }}
        </p>
      </section>

      <section v-if="data.top_rated.length" class="flex flex-col gap-2" aria-labelledby="recap-top">
        <h2 id="recap-top" class="font-display text-xl font-semibold">{{ $t('recap.topRated') }}</h2>
        <ol class="flex flex-col divide-y divide-line rounded-2xl border border-line bg-surface">
          <li v-for="book in data.top_rated" :key="`${book.title}-${book.finished_at}`" class="flex flex-col gap-1 px-4 py-3">
            <span class="font-medium">{{ book.title }}</span>
            <span v-if="book.authors.length" class="text-sm text-ink-muted">{{ authors(book.authors) }}</span>
            <RatingStars :model-value="book.rating" readonly :label="t('recap.rating', { n: book.rating })" />
          </li>
        </ol>
      </section>

      <section v-if="data.longest_book" class="flex flex-col gap-2" aria-labelledby="recap-longest">
        <h2 id="recap-longest" class="font-display text-xl font-semibold">{{ $t('recap.longestBook') }}</h2>
        <p class="flex flex-col rounded-2xl border border-line bg-surface p-4">
          <span class="font-medium">{{ data.longest_book.title }}</span>
          <span v-if="data.longest_book.authors.length" class="text-sm text-ink-muted">{{ authors(data.longest_book.authors) }}</span>
          <span class="text-sm text-ink-muted">{{ t('recap.longestBookPages', { n: formatNumber(data.longest_book.total_pages) }) }}</span>
        </p>
      </section>

      <section v-if="data.busiest_month" class="flex flex-col gap-2" aria-labelledby="recap-month">
        <h2 id="recap-month" class="font-display text-xl font-semibold">{{ $t('recap.busiestMonth') }}</h2>
        <p class="flex flex-col rounded-2xl border border-line bg-surface p-4">
          <span class="font-display text-2xl font-bold first-letter:uppercase">{{ monthName(data.busiest_month.month) }}</span>
          <span class="text-sm text-ink-muted">{{ t('recap.busiestMonthPages', { n: formatNumber(data.busiest_month.pages) }) }}</span>
        </p>
      </section>

      <section v-if="data.finished_books.length" class="flex flex-col gap-2" aria-labelledby="recap-finished">
        <h2 id="recap-finished" class="font-display text-xl font-semibold">{{ $t('recap.finished') }}</h2>
        <ul class="flex flex-col divide-y divide-line rounded-2xl border border-line bg-surface">
          <li v-for="(book, index) in data.finished_books" :key="index" class="flex items-baseline justify-between gap-3 px-4 py-3">
            <span class="min-w-0">
              <span class="block truncate font-medium">{{ book.title }}</span>
              <span v-if="book.authors.length" class="block truncate text-sm text-ink-muted">{{ authors(book.authors) }}</span>
            </span>
            <span class="shrink-0 text-right text-sm text-ink-muted">
              {{ formatDay(book.finished_at) }}
              <span v-if="book.rating" class="block" :aria-label="t('recap.rating', { n: book.rating })">★ {{ book.rating }}</span>
            </span>
          </li>
        </ul>
      </section>
    </template>

    <nav class="flex justify-between gap-3" :aria-label="$t('recap.nav')">
      <RouterLink
        v-if="hasPrevious"
        :to="{ name: 'year-recap', params: { year: year - 1 } }"
        class="inline-flex min-h-11 items-center rounded-full border border-line bg-surface px-4 text-sm font-medium"
      >
        ← {{ year - 1 }}
        <span class="sr-only">{{ $t('recap.previous') }}</span>
      </RouterLink>
      <span v-else />
      <RouterLink
        v-if="hasNext"
        :to="{ name: 'year-recap', params: { year: year + 1 } }"
        class="inline-flex min-h-11 items-center rounded-full border border-line bg-surface px-4 text-sm font-medium"
      >
        {{ year + 1 }} →
        <span class="sr-only">{{ $t('recap.next') }}</span>
      </RouterLink>
    </nav>
  </div>
</template>
