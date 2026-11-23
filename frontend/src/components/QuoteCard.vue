<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useAuthStore } from '@/stores/auth'
import { useQuotesStore } from '@/stores/quotes'
import { useSocialStore } from '@/stores/social'
import type { FeedQuote, Quote } from '@/types/api'
import ErrorNotice from './ErrorNotice.vue'
import QuoteSource from './QuoteSource.vue'

/** `readonly` is for somebody else's quote: no favorite star, no actions, and the book is not a link into the own library. */
const props = defineProps<{ quote: Quote | FeedQuote; readonly?: boolean }>()

const quotes = useQuotesStore()
const social = useSocialStore()
const auth = useAuthStore()
const { t } = useI18n()
const errorMessage = useApiErrorMessage()

const error = ref('')
const busy = ref(false)
const confirmingDelete = ref(false)
let togglingFavorite = false
let togglingLike = false

const likesCount = computed(() => props.quote.likes_count ?? 0)
const likesText = computed(() => t('quotes.likes', likesCount.value))

async function toggleLike() {
  if (togglingLike) return

  togglingLike = true
  error.value = ''

  try {
    await social.toggleLike(props.quote)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    togglingLike = false
  }
}

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
    <div v-if="'author' in quote" class="flex items-center gap-3">
      <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sunken text-sm font-semibold" aria-hidden="true">
        {{ quote.author.name.trim().charAt(0).toUpperCase() }}
      </span>
      <p class="min-w-0 text-sm text-ink-muted">
        <RouterLink
          :to="{ name: 'public-profile', params: { username: quote.author.username } }"
          class="inline-flex min-h-11 items-center font-medium text-ink underline-offset-2 hover:underline"
        >
          {{ quote.author.name }}
        </RouterLink>
        <span class="break-all"> @{{ quote.author.username }}</span>
      </p>
    </div>

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
        v-if="!readonly"
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

    <QuoteSource :quote="quote" :plain="readonly" />

    <div v-if="readonly && quote.likes_count !== undefined" class="flex items-center">
      <button
        v-if="auth.isAuthenticated"
        type="button"
        :aria-pressed="quote.liked_by_me === true"
        :aria-label="t(quote.liked_by_me ? 'quotes.unlike' : 'quotes.like', { count: likesText })"
        class="-ml-2 inline-flex min-h-11 min-w-11 items-center gap-1.5 rounded-full px-2 text-sm hover:bg-sunken"
        :class="quote.liked_by_me ? 'text-accent' : 'text-ink-muted'"
        @click="toggleLike"
      >
        <svg
          viewBox="0 0 24 24"
          class="size-6"
          :fill="quote.liked_by_me ? 'currentColor' : 'none'"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <path d="M12 20.5s-8.5-5.1-8.5-11A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 8.5 2.5c0 5.9-8.5 11-8.5 11z" />
        </svg>
        <span aria-hidden="true">{{ likesCount }}</span>
      </button>
      <p v-else class="text-sm text-ink-muted" data-test="likes-count">{{ likesText }}</p>
    </div>
    <p v-else-if="!readonly && likesCount > 0" class="text-xs text-ink-muted" data-test="likes-count">{{ likesText }}</p>

    <ErrorNotice v-if="error" :message="error" />

    <div v-if="!readonly && !confirmingDelete" class="flex flex-wrap gap-2 border-t border-line pt-3">
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

    <div v-else-if="!readonly" class="flex flex-col gap-2 border-t border-line pt-3">
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
