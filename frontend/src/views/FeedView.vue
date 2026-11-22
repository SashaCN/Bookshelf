<script setup lang="ts">
import { onMounted, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import LoadMore from '@/components/LoadMore.vue'
import PageHeader from '@/components/PageHeader.vue'
import QuoteCard from '@/components/QuoteCard.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLoadMore } from '@/composables/useLoadMore'
import { useSocialStore } from '@/stores/social'

const social = useSocialStore()
const errorMessage = useApiErrorMessage()
const { moreError, loadMore } = useLoadMore(() => social.feed.loadMore())

const error = ref('')

async function load() {
  error.value = ''
  moreError.value = ''

  try {
    await social.loadFeed()
  } catch (e) {
    error.value = errorMessage(e)
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

    <EmptyState v-else :title="$t('social.feed.empty.title')" :text="$t('social.feed.empty.text')">
      <RouterLink
        :to="{ name: 'profile' }"
        class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
      >
        {{ $t('social.feed.empty.action') }}
      </RouterLink>
    </EmptyState>

    <LoadMore :has-more="social.feed.hasMore" :loading="social.feed.loadingMore" :error="moreError" @more="loadMore" />
  </template>
</template>
