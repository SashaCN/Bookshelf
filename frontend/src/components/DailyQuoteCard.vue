<script setup lang="ts">
import { onMounted } from 'vue'
import { useQuotesStore } from '@/stores/quotes'
import QuoteSource from './QuoteSource.vue'

const quotes = useQuotesStore()

// A nice extra, never a reason to bother the reader: whatever goes wrong, the card just does not show.
onMounted(() => quotes.loadDaily().catch(() => undefined))
</script>

<template>
  <section v-if="quotes.daily" class="mt-6 flex flex-col gap-3 rounded-2xl border border-line bg-surface p-4">
    <h2 class="text-sm font-medium text-ink-muted">{{ $t('quotes.daily.title') }}</h2>
    <blockquote class="whitespace-pre-line break-words border-l-4 border-accent pl-3 font-display text-lg leading-relaxed">
      {{ quotes.daily.content }}
    </blockquote>
    <QuoteSource :quote="quotes.daily" />
    <RouterLink
      :to="{ name: 'quote-share', params: { id: quotes.daily.id } }"
      class="inline-flex min-h-11 items-center self-start rounded-lg border border-line bg-surface px-3 text-sm font-medium"
    >
      {{ $t('quotes.share') }}
    </RouterLink>
  </section>
</template>
