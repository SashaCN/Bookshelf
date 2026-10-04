<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { quotesApi } from '@/api/quotes'
import type { Quote } from '@/types/api'

/** How many of the newest quotes the book page shows; the rest are one tap away. */
const PREVIEW_COUNT = 3

const props = defineProps<{ bookId: number }>()

const quotes = ref<Quote[]>([])
const loaded = ref(false)
const failed = ref(false)

// A small fetch of its own instead of the shared list, which belongs to the quotes screen and its filters.
async function load() {
  failed.value = false

  try {
    const result = await quotesApi.list({ book: props.bookId })
    quotes.value = result.data.slice(0, PREVIEW_COUNT)
    loaded.value = true
  } catch {
    // The quotes are an extra on this page: a failure must not get in the way of the rest.
    failed.value = true
  }
}

onMounted(load)
watch(() => props.bookId, load)
</script>

<template>
  <section class="flex flex-col gap-2">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-sm font-medium">{{ $t('quotes.title') }}</h2>
      <RouterLink
        v-if="quotes.length"
        :to="{ name: 'quotes', query: { book: bookId } }"
        class="text-sm font-medium text-accent underline underline-offset-2"
      >
        {{ $t('quotes.book.all') }}
      </RouterLink>
    </div>

    <ul v-if="quotes.length" class="flex flex-col gap-2">
      <li v-for="quote in quotes" :key="quote.id" class="rounded-xl border border-line bg-surface px-3 py-2.5">
        <blockquote class="line-clamp-4 whitespace-pre-line break-words font-display leading-relaxed">{{ quote.content }}</blockquote>
        <p v-if="quote.page" class="mt-1 text-xs tabular-nums text-ink-muted">{{ $t('quotes.page', { page: quote.page }) }}</p>
      </li>
    </ul>

    <p v-else-if="failed" class="text-sm text-ink-muted">
      {{ $t('quotes.book.loadFailed') }}
      <button type="button" class="font-medium text-accent underline underline-offset-2" @click="load">
        {{ $t('common.retry') }}
      </button>
    </p>

    <p v-else-if="loaded" class="text-sm text-ink-muted">{{ $t('quotes.book.empty') }}</p>

    <RouterLink
      :to="{ name: 'quote-new', query: { book: bookId } }"
      class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-4 font-medium"
    >
      {{ $t('quotes.addQuote') }}
    </RouterLink>
  </section>
</template>
