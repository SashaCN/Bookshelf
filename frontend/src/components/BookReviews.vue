<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { socialApi } from '@/api/social'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { usePostsStore } from '@/stores/posts'
import type { Post } from '@/types/social'
import { formatNumber } from '@/utils/format'
import ErrorNotice from './ErrorNotice.vue'
import PostCard from './PostCard.vue'
import RatingStars from './RatingStars.vue'

const props = defineProps<{
  /** The book of the shared catalog (not the reader's library entry). */
  bookId: number
  /** The reader's own library entry, where a review of their own starts from. */
  userBookId: number
}>()

const { t } = useI18n()
const posts = usePostsStore()
const errorMessage = useApiErrorMessage()

const ids = ref<number[]>([])
const hasMore = ref(false)
const page = ref(1)
const reviewsCount = ref(0)
const average = ref<number | null>(null)
/** The review of the reader, named by the server: it may be on a page that has not been loaded. */
const myReviewId = ref<number | null>(null)
const loaded = ref(false)
const loading = ref(false)
const failed = ref(false)
const moreError = ref('')

/** The reviews that are loaded and still around: a deleted one drops out of the cache. */
const reviews = computed(() => ids.value.map((id) => posts.get(id)).filter((post): post is Post => post !== undefined))
/** The id of the reader's own review, unless it was in the list and has been deleted since. */
const mineId = computed(() => {
  const id = myReviewId.value
  return id !== null && ids.value.includes(id) && posts.get(id) === undefined ? null : id
})

// A small fetch of its own, so the reviews are an extra on the book page: a failure must not get in the way of the rest.
async function load(pageToLoad = 1) {
  loading.value = true
  failed.value = false
  moreError.value = ''

  try {
    const result = await socialApi.bookReviews(props.bookId, pageToLoad)
    posts.putAll(result.data)
    const fresh = result.data.map((post) => post.id)
    // A review written meanwhile pushes the last one of the previous page onto this one.
    ids.value = pageToLoad === 1 ? fresh : [...ids.value, ...fresh.filter((id) => !ids.value.includes(id))]
    hasMore.value = result.links.next !== null
    reviewsCount.value = result.meta.reviews_count
    average.value = result.meta.average_rating
    myReviewId.value = result.meta.my_review_id
    page.value = pageToLoad
    loaded.value = true
  } catch (e) {
    if (pageToLoad === 1) {
      failed.value = true
    } else {
      moreError.value = errorMessage(e)
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
// Another book starts from nothing: nothing of the previous one may show while it loads.
watch(
  () => props.bookId,
  () => {
    ids.value = []
    hasMore.value = false
    page.value = 1
    reviewsCount.value = 0
    average.value = null
    myReviewId.value = null
    loaded.value = false
    failed.value = false
    moreError.value = ''
    load()
  },
)
</script>

<template>
  <section class="flex flex-col gap-3">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-sm font-medium">{{ $t('social.reviews.title') }}</h2>
      <RouterLink
        v-if="mineId !== null"
        :to="{ name: 'community-post', params: { id: mineId } }"
        class="text-sm font-medium text-accent underline underline-offset-2"
      >
        {{ $t('social.reviews.mine') }}
      </RouterLink>
    </div>

    <div v-if="average !== null" class="flex items-center gap-3">
      <RatingStars :model-value="Math.round(average)" readonly :label="t('social.reviews.average', { rating: formatNumber(average) })" />
      <p class="text-sm tabular-nums">
        <span class="font-semibold">{{ formatNumber(average) }}</span>
        <span class="text-ink-muted"> · {{ t('social.reviews.count', { n: reviewsCount }, reviewsCount) }}</span>
      </p>
    </div>

    <ul v-if="reviews.length" class="flex flex-col gap-3">
      <li v-for="review in reviews" :key="review.id">
        <PostCard :post="review" :show-book="false" />
      </li>
    </ul>

    <p v-else-if="failed" class="text-sm text-ink-muted">
      {{ $t('social.reviews.loadFailed') }}
      <button type="button" class="font-medium text-accent underline underline-offset-2" @click="load()">
        {{ $t('common.retry') }}
      </button>
    </p>

    <p v-else-if="loaded" class="text-sm text-ink-muted">{{ $t('social.reviews.empty') }}</p>

    <ErrorNotice v-if="moreError" :message="moreError" retryable @retry="load(page + 1)" />

    <button
      v-if="hasMore && !moreError"
      type="button"
      :disabled="loading"
      class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
      @click="load(page + 1)"
    >
      {{ $t('social.reviews.more') }}
    </button>

    <RouterLink
      v-if="mineId === null"
      :to="{ name: 'community-new', query: { book: String(userBookId) } }"
      class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-4 font-medium"
    >
      {{ $t('social.reviews.write') }}
    </RouterLink>
  </section>
</template>
