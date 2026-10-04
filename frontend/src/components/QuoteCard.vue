<script setup lang="ts">
import { ref } from 'vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useQuotesStore } from '@/stores/quotes'
import type { Quote } from '@/types/api'
import ErrorNotice from './ErrorNotice.vue'
import QuoteSource from './QuoteSource.vue'

const props = defineProps<{ quote: Quote }>()

const quotes = useQuotesStore()
const errorMessage = useApiErrorMessage()

const error = ref('')
const busy = ref(false)
const confirmingDelete = ref(false)
let togglingFavorite = false

async function toggleFavorite() {
  // A second tap while the first is on its way would race it for the final state.
  if (togglingFavorite) return

  togglingFavorite = true
  error.value = ''

  try {
    await quotes.toggleFavorite(props.quote)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    togglingFavorite = false
  }
}

async function remove() {
  busy.value = true
  error.value = ''

  try {
    await quotes.remove(props.quote.id)
  } catch (e) {
    error.value = errorMessage(e)
    confirmingDelete.value = false
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <article class="flex flex-col gap-3 rounded-2xl border border-line bg-surface p-4">
    <div class="flex items-start gap-2">
      <div class="flex min-w-0 flex-1 flex-col gap-2">
        <span v-if="quote.type === 'insight'" class="self-start rounded-full bg-sunken px-2.5 py-0.5 text-xs font-medium">
          {{ $t('quotes.insight') }}
        </span>
        <blockquote class="whitespace-pre-line break-words border-l-4 border-accent pl-3 font-display text-lg leading-relaxed">
          {{ quote.content }}
        </blockquote>
      </div>

      <button
        type="button"
        :aria-pressed="quote.is_favorite"
        :aria-label="$t('quotes.favorite')"
        class="-mr-2 -mt-2 grid size-11 shrink-0 place-items-center rounded-full hover:bg-sunken"
        :class="quote.is_favorite ? 'text-accent' : 'text-ink-muted'"
        @click="toggleFavorite"
      >
        <svg
          viewBox="0 0 24 24"
          class="size-6"
          :fill="quote.is_favorite ? 'currentColor' : 'none'"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z" />
        </svg>
      </button>
    </div>

    <p v-if="quote.note" class="whitespace-pre-line break-words text-sm text-ink-muted">{{ quote.note }}</p>

    <QuoteSource :quote="quote" />

    <ErrorNotice v-if="error" :message="error" />

    <div v-if="!confirmingDelete" class="flex flex-wrap gap-2 border-t border-line pt-3">
      <RouterLink
        :to="{ name: 'quote-share', params: { id: quote.id } }"
        class="inline-flex min-h-11 items-center rounded-lg border border-line bg-surface px-3 text-sm font-medium"
      >
        {{ $t('quotes.share') }}
      </RouterLink>
      <RouterLink
        :to="{ name: 'quote-edit', params: { id: quote.id } }"
        class="inline-flex min-h-11 items-center rounded-lg border border-line bg-surface px-3 text-sm font-medium"
      >
        {{ $t('quotes.edit') }}
      </RouterLink>
      <button type="button" class="ml-auto min-h-11 px-2 text-sm font-medium text-danger" @click="confirmingDelete = true">
        {{ $t('quotes.remove') }}
      </button>
    </div>

    <div v-else class="flex flex-col gap-2 border-t border-line pt-3">
      <p class="text-sm">{{ $t('quotes.removeConfirm') }}</p>
      <div class="flex gap-2">
        <button
          type="button"
          :disabled="busy"
          class="min-h-11 flex-1 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
          @click="remove"
        >
          {{ $t('quotes.removeYes') }}
        </button>
        <button
          type="button"
          class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-4 font-medium"
          @click="confirmingDelete = false"
        >
          {{ $t('common.cancel') }}
        </button>
      </div>
    </div>
  </article>
</template>
