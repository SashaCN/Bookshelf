<script setup lang="ts">
import { onMounted, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import QuoteCard from '@/components/QuoteCard.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useSocialStore } from '@/stores/social'

const social = useSocialStore()
const errorMessage = useApiErrorMessage()

const error = ref('')
const moreError = ref('')

async function load() {
  error.value = ''
  moreError.value = ''

  try {
    await social.loadFeed()
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function loadMore() {
  moreError.value = ''

  try {
    await social.feed.loadMore()
  } catch (e) {
    moreError.value = errorMessage(e)
  }
}

onMounted(load)
</script>

<template>
  <PageHeader :title="$t('social.feed.title')" :back-to="{ name: 'home' }" />

  <ErrorNotice v-if="error" :message="error" retryable @retry="load" />

  <p v-else-if="!social.feed.loaded" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <template v-else>
    <ul v-if="social.feed.items.length" class="flex flex-col gap-3">
      <li v-for="quote in social.feed.items" :key="quote.id">
        <QuoteCard :quote="quote" readonly />
      </li>
    </ul>

    <EmptyState v-else :title="$t('social.feed.empty.title')" :text="$t('social.feed.empty.text')" />

    <ErrorNotice v-if="moreError" :message="moreError" retryable class="mt-4" @retry="loadMore" />

    <button
      v-if="social.feed.hasMore && !moreError"
      type="button"
      :disabled="social.feed.loadingMore"
      class="mt-4 min-h-11 w-full rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
      @click="loadMore"
    >
      {{ $t('quotes.more') }}
    </button>
  </template>
</template>
