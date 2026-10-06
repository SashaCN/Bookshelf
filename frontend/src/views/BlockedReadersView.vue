<script setup lang="ts">
import { onMounted, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import ReaderListItem from '@/components/ReaderListItem.vue'
import SkeletonList from '@/components/SkeletonList.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useBlocksStore } from '@/stores/blocks'
import { useFeedStore } from '@/stores/feed'

const blocks = useBlocksStore()
const feed = useFeedStore()
const errorMessage = useApiErrorMessage()

const error = ref('')
const moreError = ref('')
const actionError = ref('')
const busyHandle = ref('')

async function load() {
  error.value = ''
  moreError.value = ''

  try {
    await blocks.load()
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function loadMore() {
  moreError.value = ''

  try {
    await blocks.loadMore()
  } catch (e) {
    moreError.value = errorMessage(e)
  }
}

async function unblock(handle: string) {
  busyHandle.value = handle
  actionError.value = ''

  try {
    await blocks.unblock(handle)
    // Their posts are back in the feeds: a feed loaded before does not know them.
    feed.reset()
  } catch (e) {
    actionError.value = errorMessage(e)
  } finally {
    busyHandle.value = ''
  }
}

onMounted(load)
</script>

<template>
  <PageHeader :title="$t('social.blocked.title')" :back-to="{ name: 'profile' }" />

  <p class="mb-4 text-sm text-ink-muted">{{ $t('social.blocked.text') }}</p>

  <ErrorNotice v-if="error" :message="error" retryable @retry="load" />

  <SkeletonList v-else-if="!blocks.loaded" :count="2" />

  <template v-else>
    <ErrorNotice v-if="actionError" :message="actionError" class="mb-3" />

    <ul v-if="blocks.items.length" class="flex flex-col gap-3">
      <li v-for="reader in blocks.items" :key="reader.handle">
        <ReaderListItem :reader="reader">
          <button
            type="button"
            :disabled="busyHandle === reader.handle"
            :aria-label="$t('social.blocked.unblockLabel', { name: reader.name })"
            class="min-h-11 shrink-0 rounded-lg border border-line bg-surface px-3 text-sm font-medium disabled:opacity-60"
            @click="unblock(reader.handle)"
          >
            {{ $t('social.blocked.unblock') }}
          </button>
        </ReaderListItem>
      </li>
    </ul>

    <EmptyState v-else :title="$t('social.blocked.empty')" :text="$t('social.blocked.emptyText')" />

    <ErrorNotice v-if="moreError" :message="moreError" retryable class="mt-4" @retry="loadMore" />

    <button
      v-if="blocks.hasMore && !moreError"
      type="button"
      :disabled="blocks.loadingMore"
      class="mt-4 min-h-11 w-full rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
      @click="loadMore"
    >
      {{ $t('social.more') }}
    </button>
  </template>
</template>
