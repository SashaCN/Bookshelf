<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import BlockUserDialog from '@/components/BlockUserDialog.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import LoadMore from '@/components/LoadMore.vue'
import PageHeader from '@/components/PageHeader.vue'
import QuoteCard from '@/components/QuoteCard.vue'
import RatingStars from '@/components/RatingStars.vue'
import SegmentedControl from '@/components/SegmentedControl.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLoadMore } from '@/composables/useLoadMore'
import { formatMoment } from '@/utils/format'
import { useAuthStore } from '@/stores/auth'
import { useSocialStore } from '@/stores/social'

const props = defineProps<{ username: string }>()

const { t } = useI18n()
const social = useSocialStore()
const router = useRouter()
const auth = useAuthStore()
const errorMessage = useApiErrorMessage()

// A guest can read the page, but follows and the people lists need an account.
const loginTarget = computed(() => ({ name: 'login', query: { redirect: `/u/${encodeURIComponent(props.username)}` } }))

const notFound = ref(false)
const { moreError, loadMore } = useLoadMore(() => social.profileQuotes.loadMore())
const { moreError: moreReviewsError, loadMore: loadMoreReviews } = useLoadMore(() => social.profileReviews.loadMore())

const tab = ref<'quotes' | 'reviews'>('quotes')
const reviewsError = ref('')
const tabOptions = computed(() => [
  { value: 'quotes' as const, label: t('social.profile.tabQuotes') },
  {
    value: 'reviews' as const,
    label: social.profile && social.profile.reviews_count > 0
      ? t('social.profile.tabReviewsCount', { count: social.profile.reviews_count })
      : t('social.profile.tabReviews'),
  },
])

/** Reviews are read when the tab is first opened, so a profile that is only skimmed costs one request less. */
async function loadReviews() {
  reviewsError.value = ''

  try {
    await social.loadProfileReviews()
  } catch (e) {
    reviewsError.value = errorMessage(e)
  }
}

watch(tab, (value) => {
  if (value === 'reviews' && !social.profileReviews.loaded) void loadReviews()
})

/** An ISO timestamp as a day in the viewer's own time zone. */
const reviewDay = (iso: string) => formatMoment(iso)

const error = ref('')
const followError = ref('')
const busy = ref(false)

async function load() {
  notFound.value = false
  error.value = ''
  moreError.value = ''
  moreReviewsError.value = ''
  reviewsError.value = ''
  tab.value = 'quotes'
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

const blockOpen = ref(false)
const blocking = ref(false)
const blockError = ref('')

function openBlock() {
  blockError.value = ''
  blockOpen.value = true
}

function closeBlock() {
  if (!blocking.value) blockOpen.value = false
}

/** After the block the profile is of no use: the reader lands on the blocked list, where it can be undone. */
async function confirmBlock() {
  if (blocking.value) return

  blocking.value = true
  blockError.value = ''

  try {
    await social.blockProfile()
    await router.push({ name: 'blocks', query: { blocked: '1' } })
  } catch (e) {
    blockError.value = errorMessage(e)
  } finally {
    blocking.value = false
  }
}

const STATUS_MS = 2000
const copied = ref(false)
const showUrl = ref(false)
let copiedTimer: ReturnType<typeof setTimeout> | undefined

const profileUrl = computed(() => `${location.origin}/u/${encodeURIComponent(props.username)}`)

/** The share sheet where the browser has one, the clipboard next, a copyable field as the last resort. */
async function shareProfile() {
  const url = profileUrl.value
  showUrl.value = false

  if (typeof navigator.share === 'function') {
    try {
      await navigator.share({ title: social.profile?.name ?? props.username, url })
      return
    } catch (e) {
      // Closing the share sheet is the reader's choice, not a failure.
      if (e instanceof DOMException && e.name === 'AbortError') return
    }
  }

  try {
    await navigator.clipboard.writeText(url)
    copied.value = true
    clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => (copied.value = false), STATUS_MS)
  } catch {
    showUrl.value = true
  }
}

onBeforeUnmount(() => clearTimeout(copiedTimer))

watch(() => props.username, load, { immediate: true })
</script>

<template>
  <PageHeader :title="social.profile?.name ?? '@' + username" :back-to="auth.isAuthenticated ? { name: 'home' } : undefined" />

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
        v-if="auth.isAuthenticated"
        :to="{ name: 'followers', params: { username: social.profile.username } }"
        class="flex min-h-11 flex-col rounded-2xl border border-line bg-surface px-2 py-3"
      >
        <span class="font-display text-2xl font-bold tabular-nums">{{ social.profile.followers_count }}</span>
        <span class="text-xs text-ink-muted">{{ $t('social.profile.followers', social.profile.followers_count) }}</span>
      </RouterLink>
      <div v-else class="flex flex-col rounded-2xl border border-line bg-surface px-2 py-3">
        <span class="font-display text-2xl font-bold tabular-nums">{{ social.profile.followers_count }}</span>
        <span class="text-xs text-ink-muted">{{ $t('social.profile.followers', social.profile.followers_count) }}</span>
      </div>
      <RouterLink
        v-if="auth.isAuthenticated"
        :to="{ name: 'following', params: { username: social.profile.username } }"
        class="flex min-h-11 flex-col rounded-2xl border border-line bg-surface px-2 py-3"
      >
        <span class="font-display text-2xl font-bold tabular-nums">{{ social.profile.following_count }}</span>
        <span class="text-xs text-ink-muted">{{ $t('social.profile.following', social.profile.following_count) }}</span>
      </RouterLink>
      <div v-else class="flex flex-col rounded-2xl border border-line bg-surface px-2 py-3">
        <span class="font-display text-2xl font-bold tabular-nums">{{ social.profile.following_count }}</span>
        <span class="text-xs text-ink-muted">{{ $t('social.profile.following', social.profile.following_count) }}</span>
      </div>
    </div>

    <ErrorNotice v-if="followError" :message="followError" retryable class="mb-3" @retry="toggleFollow" />

    <RouterLink
      v-if="!auth.isAuthenticated"
      :to="loginTarget"
      class="mb-6 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
    >
      {{ $t('social.profile.loginToFollow') }}
    </RouterLink>

    <button
      v-else-if="!social.profile.is_me"
      type="button"
      :disabled="busy"
      :aria-pressed="social.profile.is_following"
      class="mb-6 min-h-11 w-full rounded-lg px-4 font-semibold disabled:opacity-60"
      :class="social.profile.is_following ? 'border border-line bg-surface' : 'bg-accent text-on-accent'"
      @click="toggleFollow"
    >
      {{ social.profile.is_following ? $t('social.profile.unfollow') : $t('social.profile.follow') }}
    </button>

    <div v-if="auth.isAuthenticated && social.profile.is_me" class="mb-6">
      <button type="button" class="min-h-11 w-full rounded-lg border border-line bg-surface px-4 font-semibold" @click="shareProfile">
        {{ $t('social.profile.share') }}
      </button>
      <p role="status" class="mt-2 text-center text-sm text-ink-muted">{{ copied ? $t('social.profile.linkCopied') : '' }}</p>
      <input
        v-if="showUrl"
        type="text"
        readonly
        :value="profileUrl"
        :aria-label="$t('social.profile.copyHint')"
        class="mt-2 min-h-11 w-full rounded-lg border border-line bg-surface px-3"
        @focus="($event.target as HTMLInputElement).select()"
      >
    </div>

    <div v-if="auth.isAuthenticated && !social.profile.is_me" class="-mt-3 mb-6">
      <button
        type="button"
        data-test="block"
        class="min-h-11 w-full rounded-lg px-4 text-sm font-medium text-danger"
        @click="openBlock"
      >
        {{ $t('social.profile.block') }}
      </button>
    </div>

    <BlockUserDialog
      v-if="blockOpen"
      :username="social.profile.username"
      :pending="blocking"
      :error="blockError"
      @cancel="closeBlock"
      @confirm="confirmBlock"
    />

    <SegmentedControl v-model="tab" :label="$t('social.profile.tabsLabel')" :options="tabOptions" hide-label class="mb-4" />

    <section v-if="tab === 'quotes'" :aria-label="$t('social.profile.tabQuotes')">
      <ul v-if="social.profileQuotes.items.length" class="flex flex-col gap-3">
        <li v-for="quote in social.profileQuotes.items" :key="quote.id">
          <QuoteCard :quote="quote" readonly />
        </li>
      </ul>

      <p v-else-if="social.profileQuotes.loaded" class="text-ink-muted">{{ $t('social.profile.noQuotes') }}</p>

      <LoadMore :has-more="social.profileQuotes.hasMore" :loading="social.profileQuotes.loadingMore" :error="moreError" @more="loadMore" />
    </section>

    <section v-else :aria-label="$t('social.profile.tabReviews')">
      <ErrorNotice v-if="reviewsError" :message="reviewsError" retryable @retry="loadReviews" />

      <p v-else-if="!social.profileReviews.loaded" class="text-ink-muted">{{ $t('common.loading') }}</p>

      <ul v-else-if="social.profileReviews.items.length" class="flex flex-col gap-3">
        <li v-for="review in social.profileReviews.items" :key="review.id" class="rounded-2xl border border-line bg-surface p-4">
          <p class="font-medium">{{ review.book.title }}</p>
          <p v-if="review.book.authors.length" class="text-sm text-ink-muted">{{ review.book.authors.join(', ') }}</p>
          <div class="mt-2 flex items-center gap-3">
            <RatingStars
              v-if="review.rating !== null"
              :model-value="review.rating"
              readonly
              :label="$t('social.profile.reviewRating', { rating: review.rating })"
            />
            <time :datetime="review.reviewed_at" class="text-sm text-ink-muted">{{ reviewDay(review.reviewed_at) }}</time>
          </div>
          <p class="mt-2 whitespace-pre-line break-words">{{ review.review }}</p>
        </li>
      </ul>

      <p v-else class="text-ink-muted">{{ $t('social.profile.noReviews') }}</p>

      <LoadMore
        v-if="!reviewsError"
        :has-more="social.profileReviews.hasMore"
        :loading="social.profileReviews.loadingMore"
        :error="moreReviewsError"
        @more="loadMoreReviews"
      />
    </section>
  </template>
</template>
