<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLoadMore } from '@/composables/useLoadMore'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import LoadMore from '@/components/LoadMore.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useSocialStore } from '@/stores/social'

const social = useSocialStore()
const route = useRoute()
const errorMessage = useApiErrorMessage()

const { moreError, loadMore } = useLoadMore(() => social.blocks.loadMore())

const error = ref('')
const unblockError = ref('')
/** Set by the profile page that has just blocked somebody, so the reader sees that it worked. */
const justBlocked = route.query.blocked === '1'
const pending = ref<string[]>([])

async function load() {
  error.value = ''
  moreError.value = ''

  try {
    await social.loadBlocks()
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function unblock(username: string) {
  if (pending.value.includes(username)) return

  pending.value.push(username)
  unblockError.value = ''

  try {
    await social.unblock(username)
  } catch (e) {
    unblockError.value = errorMessage(e)
  } finally {
    pending.value = pending.value.filter((name) => name !== username)
  }
}

void load()
</script>

<template>
  <PageHeader :title="$t('social.blocks.title')" :back-to="{ name: 'profile' }" />

  <p v-if="justBlocked" role="status" class="mb-3 rounded-lg bg-surface px-3 py-2 text-sm text-ink-muted">{{ $t('social.blocks.blocked') }}</p>

  <ErrorNotice v-if="error" :message="error" retryable @retry="load" />

  <p v-else-if="!social.blocks.loaded" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <template v-else>
    <ErrorNotice v-if="unblockError" :message="unblockError" class="mb-3" />

    <ul v-if="social.blocks.items.length" class="flex flex-col gap-2">
      <li
        v-for="person in social.blocks.items"
        :key="person.username"
        class="flex items-center justify-between gap-3 rounded-2xl border border-line bg-surface px-4 py-3"
      >
        <span class="flex min-w-0 flex-col">
          <span class="truncate font-medium">{{ person.name }}</span>
          <span class="truncate text-sm text-ink-muted">@{{ person.username }}</span>
        </span>
        <button
          type="button"
          :disabled="pending.includes(person.username)"
          :aria-label="$t('social.blocks.unblockNamed', { handle: '@' + person.username })"
          class="min-h-11 shrink-0 rounded-lg border border-line bg-surface px-3 font-medium disabled:opacity-60"
          @click="unblock(person.username)"
        >
          {{ $t('social.blocks.unblock') }}
        </button>
      </li>
    </ul>

    <EmptyState v-else :title="$t('social.blocks.empty.title')" :text="$t('social.blocks.empty.text')" />

    <LoadMore :has-more="social.blocks.hasMore" :loading="social.blocks.loadingMore" :error="moreError" @more="loadMore" />
  </template>
</template>
