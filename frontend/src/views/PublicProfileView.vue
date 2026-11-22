<script setup lang="ts">
import { ref, watch } from 'vue'
import { ApiError } from '@/api/http'
import ErrorNotice from '@/components/ErrorNotice.vue'
import LoadMore from '@/components/LoadMore.vue'
import PageHeader from '@/components/PageHeader.vue'
import QuoteCard from '@/components/QuoteCard.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLoadMore } from '@/composables/useLoadMore'
import { useSocialStore } from '@/stores/social'

const props = defineProps<{ username: string }>()

const social = useSocialStore()
const errorMessage = useApiErrorMessage()

const notFound = ref(false)
const { moreError, loadMore } = useLoadMore(() => social.profileQuotes.loadMore())

const error = ref('')
const followError = ref('')
const busy = ref(false)

async function load() {
  notFound.value = false
  error.value = ''
  moreError.value = ''
  followError.value = ''

  try {
    await social.loadProfile(props.username)
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      notFound.value = true
    } else {
      error.value = errorMessage(e)
    }
  }
}

async function toggleFollow() {
  if (!social.profile || busy.value) return

  busy.value = true
  followError.value = ''

  try {
    await social.setFollowing(!social.profile.is_following)
  } catch (e) {
    followError.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

watch(() => props.username, load, { immediate: true })
</script>

<template>
  <PageHeader :title="social.profile?.name ?? '@' + username" :back-to="{ name: 'home' }" />

  <p v-if="notFound" class="py-10 text-center text-ink-muted">{{ $t('social.profile.notFound') }}</p>

  <ErrorNotice v-else-if="error" :message="error" retryable @retry="load" />

  <p v-else-if="!social.profile" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <template v-else>
    <p class="-mt-2 mb-4 text-ink-muted">@{{ social.profile.username }}</p>

    <div class="mb-4 grid grid-cols-3 gap-2 text-center">
      <div class="rounded-2xl border border-line bg-surface px-2 py-3">
        <p class="font-display text-2xl font-bold tabular-nums">{{ social.profile.books_finished_this_year }}</p>
        <p class="text-xs text-ink-muted">{{ $t('social.profile.finishedThisYear') }}</p>
      </div>
      <RouterLink
        :to="{ name: 'followers', params: { username: social.profile.username } }"
        class="flex min-h-11 flex-col rounded-2xl border border-line bg-surface px-2 py-3"
      >
        <span class="font-display text-2xl font-bold tabular-nums">{{ social.profile.followers_count }}</span>
        <span class="text-xs text-ink-muted">{{ $t('social.profile.followers', social.profile.followers_count) }}</span>
      </RouterLink>
      <RouterLink
        :to="{ name: 'following', params: { username: social.profile.username } }"
        class="flex min-h-11 flex-col rounded-2xl border border-line bg-surface px-2 py-3"
      >
        <span class="font-display text-2xl font-bold tabular-nums">{{ social.profile.following_count }}</span>
        <span class="text-xs text-ink-muted">{{ $t('social.profile.following', social.profile.following_count) }}</span>
      </RouterLink>
    </div>

    <ErrorNotice v-if="followError" :message="followError" retryable class="mb-3" @retry="toggleFollow" />

    <button
      v-if="!social.profile.is_me"
      type="button"
      :disabled="busy"
      :aria-pressed="social.profile.is_following"
      class="mb-6 min-h-11 w-full rounded-lg px-4 font-semibold disabled:opacity-60"
      :class="social.profile.is_following ? 'border border-line bg-surface' : 'bg-accent text-on-accent'"
      @click="toggleFollow"
    >
      {{ social.profile.is_following ? $t('social.profile.unfollow') : $t('social.profile.follow') }}
    </button>

    <h2 class="mb-3 font-display text-xl font-semibold">{{ $t('social.profile.quotes') }}</h2>

    <ul v-if="social.profileQuotes.items.length" class="flex flex-col gap-3">
      <li v-for="quote in social.profileQuotes.items" :key="quote.id">
        <QuoteCard :quote="quote" readonly />
      </li>
    </ul>

    <p v-else-if="social.profileQuotes.loaded" class="text-ink-muted">{{ $t('social.profile.noQuotes') }}</p>

    <LoadMore :has-more="social.profileQuotes.hasMore" :loading="social.profileQuotes.loadingMore" :error="moreError" @more="loadMore" />
  </template>
</template>
