<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLoadMore } from '@/composables/useLoadMore'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import LoadMore from '@/components/LoadMore.vue'
import PageHeader from '@/components/PageHeader.vue'
import { ApiError } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { useSocialStore } from '@/stores/social'

const props = defineProps<{ username: string; kind: 'followers' | 'following' }>()

const social = useSocialStore()
const auth = useAuthStore()
const errorMessage = useApiErrorMessage()

const { moreError, loadMore } = useLoadMore(() => social.people.loadMore())

const error = ref('')
const notFound = ref(false)

const title = computed(() => (props.kind === 'followers' ? 'social.people.followersTitle' : 'social.people.followingTitle'))
const empty = computed(() => (props.kind === 'followers' ? 'social.people.emptyFollowers' : 'social.people.emptyFollowing'))

/** Opening one's own followers list is what "seen" means; a failure must not break the list. */
async function markSeen() {
  if (props.kind !== 'followers' || auth.user?.username !== props.username) return

  try {
    await auth.markFollowersSeen()
  } catch {
    // The badge simply stays until the next visit.
  }
}

async function load() {
  error.value = ''
  moreError.value = ''
  notFound.value = false

  try {
    await social.loadPeople(props.username, props.kind)
    await markSeen()
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      notFound.value = true
    } else {
      error.value = errorMessage(e)
    }
  }
}

watch(() => [props.username, props.kind], load, { immediate: true })
</script>

<template>
  <PageHeader :title="$t(title)" :back-to="{ name: 'public-profile', params: { username } }" />

  <p v-if="notFound" class="py-10 text-center text-ink-muted">{{ $t('social.profile.notFound') }}</p>

  <ErrorNotice v-else-if="error" :message="error" retryable @retry="load" />

  <p v-else-if="!social.people.loaded" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <template v-else>
    <ul v-if="social.people.items.length" class="flex flex-col gap-2">
      <li v-for="person in social.people.items" :key="person.username">
        <RouterLink
          :to="{ name: 'public-profile', params: { username: person.username } }"
          class="flex min-h-11 flex-col rounded-2xl border bg-surface px-4 py-3"
          :class="person.is_new ? 'border-accent' : 'border-line'"
        >
          <span class="font-medium">
            {{ person.name }}
            <span
              v-if="person.is_new"
              data-test="new-chip"
              class="ml-2 rounded-full bg-accent px-2 py-0.5 align-middle text-xs font-semibold text-on-accent"
            >{{ $t('social.people.newChip') }}</span>
          </span>
          <span class="text-sm text-ink-muted">@{{ person.username }}</span>
        </RouterLink>
      </li>
    </ul>

    <EmptyState v-else :title="$t('social.people.emptyTitle')" :text="$t(empty)" />

    <LoadMore :has-more="social.people.hasMore" :loading="social.people.loadingMore" :error="moreError" @more="loadMore" />
  </template>
</template>
