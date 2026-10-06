<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { isApiErrorCode } from '@/api/errors'
import { ApiError } from '@/api/http'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import RatingStars from '@/components/RatingStars.vue'
import SegmentedControl from '@/components/SegmentedControl.vue'
import SkeletonList from '@/components/SkeletonList.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useFeedStore } from '@/stores/feed'
import { useLibraryStore } from '@/stores/library'
import { usePostsStore } from '@/stores/posts'
import { useQuotesStore } from '@/stores/quotes'
import type { Quote } from '@/types/api'
import type { NewPostPayload, PostType } from '@/types/social'

const COMMENT_MAX = 2000
const CONTENT_MAX = 5000

type Field = 'quote' | 'book' | 'rating' | 'content' | 'comment'
/** The names the server uses for the fields, by the names of this form. */
const serverFields: Record<string, Field> = {
  quote_id: 'quote',
  user_book_id: 'book',
  rating: 'rating',
  content: 'content',
  comment: 'comment',
}

const { t, te } = useI18n()
const route = useRoute()
const router = useRouter()
const library = useLibraryStore()
const quotes = useQuotesStore()
const posts = usePostsStore()
const feed = useFeedStore()
const errorMessage = useApiErrorMessage()

function idFrom(value: unknown): number | undefined {
  const id = Number(value)
  return Number.isInteger(id) && id > 0 ? id : undefined
}

const type = ref<PostType>(idFrom(route.query.book) !== undefined && idFrom(route.query.quote) === undefined ? 'review' : 'quote')
const quoteId = ref<number | null>(null)
const comment = ref('')
const bookId = ref<number | ''>('')
const rating = ref<number | null>(null)
const content = ref('')
/** The quote the reader came here to publish, when it is not among the first quotes of the list. */
const requestedQuote = ref<Quote | null>(null)

const ready = ref(false)
const loadError = ref('')
const quotesMoreError = ref('')
const missingQuote = ref(false)
const saving = ref(false)
const error = ref('')
const fieldErrors = reactive<Partial<Record<Field, string>>>({})

const typeOptions = computed(() => [
  { value: 'quote' as const, label: t('social.compose.quote') },
  { value: 'review' as const, label: t('social.compose.review') },
])

/** The quotes to choose from: the one asked for first, then the reader's newest. */
const choices = computed(() => {
  const requested = requestedQuote.value
  return requested && !quotes.items.some((quote) => quote.id === requested.id) ? [requested, ...quotes.items] : quotes.items
})

/** Whether there is something to publish: a quote to pick, or a book to write about. */
const hasChoices = computed(() => (type.value === 'quote' ? choices.value.length > 0 : library.items.length > 0))

async function load() {
  ready.value = false
  loadError.value = ''
  missingQuote.value = false

  try {
    await Promise.all([library.load(), quotes.load({})])

    const wantedQuote = idFrom(route.query.quote)
    if (wantedQuote !== undefined) {
      try {
        requestedQuote.value = await quotes.get(wantedQuote)
        quoteId.value = wantedQuote
      } catch (e) {
        if (!(e instanceof ApiError && e.status === 404)) throw e
        missingQuote.value = true
      }
    }

    const wantedBook = idFrom(route.query.book)
    if (wantedBook !== undefined && library.find(wantedBook)) {
      bookId.value = wantedBook
    }

    ready.value = true
  } catch (e) {
    loadError.value = errorMessage(e)
  }
}

onMounted(load)

async function loadMoreQuotes() {
  quotesMoreError.value = ''

  try {
    await quotes.loadMore()
  } catch (e) {
    quotesMoreError.value = errorMessage(e)
  }
}

function leave() {
  if (window.history.state?.back) {
    router.back()
  } else {
    router.replace({ name: 'community' })
  }
}

/** Show 422 answers next to the fields they are about. Returns false when no field is to blame. */
function showFieldErrors(e: ApiError): boolean {
  let shown = false

  for (const [serverField, field] of Object.entries(serverFields)) {
    const messages = e.fieldErrors[serverField]
    if (!messages?.length) continue

    const code = messages.find(isApiErrorCode)
    fieldErrors[field] = code && te(`apiErrors.${code}`) ? t(`apiErrors.${code}`) : t(`social.compose.errors.${field}`)
    shown = true
  }

  return shown
}

/** What the reader entered, or null (with the reasons shown) when something is missing. */
function buildPayload(): NewPostPayload | null {
  if (type.value === 'quote') {
    if (quoteId.value === null) {
      fieldErrors.quote = t('social.compose.errors.quote')
      return null
    }

    return { type: 'quote', quote_id: quoteId.value, comment: comment.value.trim() || null }
  }

  if (bookId.value === '') fieldErrors.book = t('social.compose.errors.book')
  if (rating.value === null) fieldErrors.rating = t('social.compose.errors.rating')
  if (bookId.value === '' || rating.value === null) return null

  return { type: 'review', user_book_id: bookId.value, rating: rating.value, content: content.value.trim() || null }
}

async function submit() {
  error.value = ''
  for (const field of Object.keys(fieldErrors) as Field[]) delete fieldErrors[field]

  const payload = buildPayload()
  if (payload === null) return

  saving.value = true

  try {
    const post = await posts.create(payload)
    feed.prepend(post)
    await router.replace({ name: 'community-post', params: { id: post.id } })
  } catch (e) {
    if (!(e instanceof ApiError && e.status === 422 && showFieldErrors(e))) {
      error.value = errorMessage(e)
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <PageHeader :title="$t('social.compose.title')" :back-to="{ name: 'community' }" />

  <ErrorNotice v-if="loadError" :message="loadError" retryable @retry="load" />

  <SkeletonList v-else-if="!ready" />

  <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
    <ErrorNotice v-if="error" :message="error" />

    <p class="text-sm text-ink-muted">{{ $t('social.compose.privacy') }}</p>

    <SegmentedControl v-model="type" :label="$t('social.compose.type')" :options="typeOptions" />

    <template v-if="type === 'quote'">
      <p v-if="missingQuote" role="alert" class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">
        {{ $t('social.compose.quoteMissing') }}
      </p>

      <div
        v-if="!choices.length"
        class="flex flex-col items-start gap-2 rounded-2xl border border-dashed border-line p-4"
      >
        <p class="text-ink-muted">{{ $t('social.compose.noQuotes') }}</p>
        <RouterLink
          :to="{ name: 'quotes' }"
          class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
        >
          {{ $t('social.compose.toQuotes') }}
        </RouterLink>
      </div>

      <template v-else>
        <fieldset
          class="flex flex-col gap-2"
          :aria-describedby="fieldErrors.quote ? 'post-quote-error' : undefined"
        >
          <legend class="mb-1 text-sm font-medium">{{ $t('social.compose.quotePick') }}</legend>
          <label
            v-for="quote in choices"
            :key="quote.id"
            class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border p-3"
            :class="quoteId === quote.id ? 'border-accent bg-sunken' : 'border-line bg-surface'"
          >
            <input v-model="quoteId" type="radio" name="post-quote" :value="quote.id" class="mt-1.5 size-4 shrink-0 accent-accent">
            <span class="flex min-w-0 flex-col gap-0.5">
              <span class="line-clamp-3 whitespace-pre-line break-words font-display leading-relaxed">{{ quote.content }}</span>
              <span class="truncate text-sm text-ink-muted">{{ quote.book.title }}</span>
            </span>
          </label>
          <p v-if="fieldErrors.quote" id="post-quote-error" role="alert" class="text-sm text-danger">{{ fieldErrors.quote }}</p>
        </fieldset>

        <ErrorNotice v-if="quotesMoreError" :message="quotesMoreError" retryable @retry="loadMoreQuotes" />
        <button
          v-else-if="quotes.hasMore"
          type="button"
          :disabled="quotes.loadingMore"
          class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
          @click="loadMoreQuotes"
        >
          {{ $t('social.more') }}
        </button>

        <p class="text-sm text-ink-muted">{{ $t('social.compose.snapshot') }}</p>

        <div class="flex flex-col gap-1.5">
          <label for="post-comment" class="text-sm font-medium">
            {{ $t('social.compose.comment') }}
            <span class="font-normal text-ink-muted">({{ $t('social.compose.commentHint') }})</span>
          </label>
          <textarea
            id="post-comment"
            v-model="comment"
            rows="3"
            :maxlength="COMMENT_MAX"
            :aria-invalid="fieldErrors.comment ? 'true' : undefined"
            :aria-describedby="fieldErrors.comment ? 'post-comment-error' : undefined"
            class="rounded-lg border border-line bg-surface px-3 py-2 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
          />
          <p v-if="fieldErrors.comment" id="post-comment-error" class="text-sm text-danger">{{ fieldErrors.comment }}</p>
          <p class="text-right text-xs tabular-nums text-ink-muted">
            {{ $t('social.compose.counter', { count: comment.length, max: COMMENT_MAX }) }}
          </p>
        </div>
      </template>
    </template>

    <template v-else>
      <div
        v-if="!library.items.length"
        class="flex flex-col items-start gap-2 rounded-2xl border border-dashed border-line p-4"
      >
        <p class="text-ink-muted">{{ $t('social.compose.noBooks') }}</p>
        <RouterLink
          :to="{ name: 'library' }"
          class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
        >
          {{ $t('social.compose.toLibrary') }}
        </RouterLink>
      </div>

      <template v-else>
        <div class="flex flex-col gap-1.5">
          <label for="post-book" class="text-sm font-medium">{{ $t('social.compose.book') }}</label>
          <select
            id="post-book"
            v-model="bookId"
            required
            :aria-invalid="fieldErrors.book ? 'true' : undefined"
            :aria-describedby="fieldErrors.book ? 'post-book-error' : undefined"
            class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
          >
            <option value="" disabled>{{ $t('social.compose.bookPlaceholder') }}</option>
            <option v-for="userBook in library.byTitle" :key="userBook.id" :value="userBook.id">
              {{ userBook.book.title }}
            </option>
          </select>
          <p v-if="fieldErrors.book" id="post-book-error" role="alert" class="text-sm text-danger">{{ fieldErrors.book }}</p>
        </div>

        <div class="flex flex-col gap-1">
          <p class="text-sm font-medium">{{ $t('social.compose.rating') }}</p>
          <RatingStars v-model="rating" :label="$t('social.compose.rating')" />
          <p v-if="fieldErrors.rating" role="alert" class="text-sm text-danger">{{ fieldErrors.rating }}</p>
        </div>

        <div class="flex flex-col gap-1.5">
          <label for="post-content" class="text-sm font-medium">
            {{ $t('social.compose.content') }}
            <span class="font-normal text-ink-muted">({{ $t('social.compose.contentHint') }})</span>
          </label>
          <textarea
            id="post-content"
            v-model="content"
            rows="6"
            :maxlength="CONTENT_MAX"
            :aria-invalid="fieldErrors.content ? 'true' : undefined"
            :aria-describedby="fieldErrors.content ? 'post-content-error' : undefined"
            class="rounded-lg border border-line bg-surface px-3 py-2 text-base leading-relaxed outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
          />
          <p v-if="fieldErrors.content" id="post-content-error" class="text-sm text-danger">{{ fieldErrors.content }}</p>
          <p class="text-right text-xs tabular-nums text-ink-muted">
            {{ $t('social.compose.counter', { count: content.length, max: CONTENT_MAX }) }}
          </p>
        </div>
      </template>
    </template>

    <div class="flex gap-2">
      <button
        v-if="hasChoices"
        type="submit"
        :disabled="saving"
        class="min-h-11 flex-1 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      >
        {{ saving ? $t('social.compose.publishing') : $t('social.compose.submit') }}
      </button>
      <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium" @click="leave">
        {{ $t('common.cancel') }}
      </button>
    </div>
  </form>
</template>
