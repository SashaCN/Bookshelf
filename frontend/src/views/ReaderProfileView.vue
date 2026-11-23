<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { ApiError } from '@/api/http'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import PostCard from '@/components/PostCard.vue'
import ReaderAvatar from '@/components/ReaderAvatar.vue'
import SkeletonList from '@/components/SkeletonList.vue'
import StatTile from '@/components/StatTile.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLibraryStore } from '@/stores/library'
import { useProfilesStore } from '@/stores/profiles'
import { formatNumber } from '@/utils/format'

const props = defineProps<{ handle: string }>()

const profiles = useProfilesStore()
const library = useLibraryStore()
const errorMessage = useApiErrorMessage()

const notFound = ref(false)
const error = ref('')
const actionError = ref('')
const postsError = ref('')
const moreError = ref('')
const confirmingBlock = ref(false)
const busy = ref(false)

async function loadPosts() {
  postsError.value = ''

  try {
    await profiles.loadPosts(props.handle)
  } catch (e) {
    postsError.value = errorMessage(e)
  }
}

async function load() {
  notFound.value = false
  error.value = ''
  actionError.value = ''
  moreError.value = ''
  confirmingBlock.value = false

  try {
    await profiles.load(props.handle)
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      notFound.value = true
    } else {
      error.value = errorMessage(e)
    }
    return
  }

  // The posts of a reader who is blocked are not shown, and the server would give none anyway.
  if (!profiles.profile?.is_blocked) {
    await loadPosts()
  }
}

onMounted(() => {
  // Only feeds the links from the books of the posts to the library: without it the titles are simply not links.
  library.load().catch(() => undefined)
  load()
})
watch(() => props.handle, load)

async function loadMore() {
  moreError.value = ''

  try {
    await profiles.loadMorePosts()
  } catch (e) {
    moreError.value = errorMessage(e)
  }
}

async function toggleFollow() {
  actionError.value = ''

  try {
    await profiles.toggleFollow()
  } catch (e) {
    actionError.value = errorMessage(e)
  }
}

async function block() {
  busy.value = true
  actionError.value = ''

  try {
    await profiles.block()
  } catch (e) {
    actionError.value = errorMessage(e)
  } finally {
    busy.value = false
    confirmingBlock.value = false
  }
}

async function unblock() {
  busy.value = true
  actionError.value = ''

  try {
    await profiles.unblock()

    if (profiles.profile && !profiles.profile.is_blocked) {
      await loadPosts()
    }
  } catch (e) {
    actionError.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <PageHeader :title="profiles.profile?.name ?? $t('social.profile.title')" :back-to="{ name: 'community' }" />

  <p v-if="notFound" class="py-10 text-center text-ink-muted">{{ $t('social.profile.notFound') }}</p>

  <ErrorNotice v-else-if="error && !profiles.profile" :message="error" retryable @retry="load" />

  <SkeletonList v-else-if="!profiles.profile || profiles.profile.handle !== handle" :count="2" tall />

  <div v-else class="flex flex-col gap-6">
    <section class="flex flex-col gap-4">
      <div class="flex items-center gap-4">
        <ReaderAvatar :name="profiles.profile.name" size="lg" />
        <div class="flex min-w-0 flex-col">
          <h2 class="truncate font-display text-2xl font-bold">{{ profiles.profile.name }}</h2>
          <p class="truncate text-ink-muted">@{{ profiles.profile.handle }}</p>
        </div>
      </div>

      <p v-if="profiles.profile.bio" class="whitespace-pre-line break-words">{{ profiles.profile.bio }}</p>

      <div class="grid grid-cols-3 gap-2">
        <StatTile :label="$t('social.profile.posts')" :value="formatNumber(profiles.profile.posts_count)" />
        <StatTile :label="$t('social.profile.followers')" :value="formatNumber(profiles.profile.followers_count)" />
        <StatTile :label="$t('social.profile.following')" :value="formatNumber(profiles.profile.following_count)" />
      </div>

      <ErrorNotice v-if="actionError" :message="actionError" />

      <RouterLink
        v-if="profiles.profile.is_me"
        :to="{ name: 'profile' }"
        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-4 font-medium"
      >
        {{ $t('social.profile.editProfile') }}
      </RouterLink>

      <template v-else>
        <p v-if="profiles.profile.is_blocked" role="status" class="rounded-lg bg-sunken px-3 py-2 text-sm">
          {{ $t('social.profile.blockedNote') }}
        </p>

        <button
          v-else
          type="button"
          :aria-pressed="profiles.profile.is_following"
          :aria-label="$t('social.profile.followLabel', { name: profiles.profile.name })"
          class="min-h-11 rounded-lg px-4 font-semibold"
          :class="profiles.profile.is_following ? 'border border-line bg-surface' : 'bg-accent text-on-accent'"
          @click="toggleFollow"
        >
          {{ profiles.profile.is_following ? $t('social.profile.followed') : $t('social.profile.follow') }}
        </button>

        <div v-if="confirmingBlock" class="flex flex-col gap-2 rounded-2xl border border-line p-3">
          <p class="text-sm">{{ $t('social.profile.blockConfirm') }}</p>
          <div class="flex gap-2">
            <button
              type="button"
              :disabled="busy"
              class="min-h-11 flex-1 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
              @click="block"
            >
              {{ $t('social.profile.blockYes') }}
            </button>
            <button
              type="button"
              class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-4 font-medium"
              @click="confirmingBlock = false"
            >
              {{ $t('common.cancel') }}
            </button>
          </div>
        </div>

        <button
          v-else-if="profiles.profile.is_blocked"
          type="button"
          :disabled="busy"
          class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
          @click="unblock"
        >
          {{ $t('social.profile.unblock') }}
        </button>

        <button v-else type="button" class="min-h-11 self-start text-sm font-medium text-danger" @click="confirmingBlock = true">
          {{ $t('social.profile.block') }}
        </button>
      </template>
    </section>

    <section v-if="!profiles.profile.is_blocked" class="flex flex-col gap-3" aria-labelledby="reader-posts-title">
      <h2 id="reader-posts-title" class="font-display text-xl font-semibold">{{ $t('social.profile.posts') }}</h2>

      <ErrorNotice v-if="postsError" :message="postsError" retryable @retry="loadPosts" />

      <SkeletonList v-else-if="!profiles.postsLoaded" :count="2" />

      <template v-else>
        <ul v-if="profiles.items.length" class="flex flex-col gap-3">
          <li v-for="post in profiles.items" :key="post.id">
            <PostCard :post="post" />
          </li>
        </ul>

        <EmptyState
          v-else
          :title="$t('social.profile.noPosts')"
          :text="profiles.profile.is_me ? $t('social.profile.noPostsMe') : $t('social.profile.noPostsText')"
        />

        <ErrorNotice v-if="moreError" :message="moreError" retryable @retry="loadMore" />

        <button
          v-if="profiles.hasMorePosts && !moreError"
          type="button"
          :disabled="profiles.loadingMorePosts"
          class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
          @click="loadMore"
        >
          {{ $t('social.more') }}
        </button>
      </template>
    </section>
  </div>
</template>
