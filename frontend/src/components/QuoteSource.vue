<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Quote } from '@/types/api'

const props = defineProps<{ quote: Quote }>()
const { t } = useI18n()

/** "James Clear · с. 42": whatever is known besides the title. */
const details = computed(() => {
  const parts = [props.quote.book.authors.join(', '), props.quote.page ? t('quotes.page', { page: props.quote.page }) : '']
  const known = parts.filter(Boolean).join(' · ')
  return known ? `· ${known}` : ''
})
</script>

<template>
  <p class="text-sm text-ink-muted">
    <RouterLink
      :to="{ name: 'book', params: { id: quote.user_book_id } }"
      class="font-medium text-ink underline-offset-2 hover:underline"
    >
      {{ quote.book.title }}
    </RouterLink>
    {{ details }}
  </p>
</template>
