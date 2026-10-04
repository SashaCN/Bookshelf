<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { isApiErrorCode } from '@/api/errors'
import { ApiError } from '@/api/http'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import SegmentedControl from '@/components/SegmentedControl.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLibraryStore } from '@/stores/library'
import { useQuotesStore } from '@/stores/quotes'
import type { Quote, QuoteType } from '@/types/api'

const MAX_LENGTH = 2000
/** The most pages the server accepts; the book's own page count is the tighter limit when it is known. */
const MAX_PAGE = 20000

type Field = 'content' | 'note' | 'page'
const fields: Field[] = ['content', 'note', 'page']

/** Set when editing; without it the form adds a new quote. */
const props = defineProps<{ id?: number }>()

const { t, te } = useI18n()
const route = useRoute()
const router = useRouter()
const library = useLibraryStore()
const quotes = useQuotesStore()
const errorMessage = useApiErrorMessage()

const editing = computed(() => props.id !== undefined)

// The page is kept as text on purpose: v-model on a number input would turn it into a number (see PageJumpForm).
const form = reactive({ type: 'quote' as QuoteType, content: '', note: '', page: '' })
const bookId = ref<number | ''>('')
const quote = ref<Quote | null>(null)

const ready = ref(false)
const notFound = ref(false)
const loadError = ref('')
const saving = ref(false)
const error = ref('')
const fieldErrors = reactive<Partial<Record<Field, string>>>({})

const typeOptions = computed(() => [
  { value: 'quote' as const, label: t('quotes.type.quote') },
  { value: 'insight' as const, label: t('quotes.type.insight') },
])

const selectedBook = computed(() => {
  const id = quote.value ? quote.value.user_book_id : bookId.value
  return id === '' ? undefined : library.find(id)
})
const maxPage = computed(() => selectedBook.value?.total_pages ?? MAX_PAGE)

function fill(source: Quote) {
  form.type = source.type
  form.content = source.content
  form.note = source.note ?? ''
  form.page = source.page === null ? '' : String(source.page)
}

async function load() {
  ready.value = false
  notFound.value = false
  loadError.value = ''
  quote.value = null

  try {
    await library.load()

    if (props.id === undefined) {
      const requested = Number(route.query.book)
      bookId.value = library.find(requested) ? requested : ''
    } else {
      quote.value = await quotes.get(props.id)
      fill(quote.value)
    }

    ready.value = true
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      notFound.value = true
    } else {
      loadError.value = errorMessage(e)
    }
  }
}

onMounted(load)
watch(() => props.id, load)

function toPage(value: string): number | null {
  const page = Number.parseInt(value, 10)
  return Number.isFinite(page) ? page : null
}

/** Back to wherever the reader came from (the book page, the list, the card), or the list when there is no such place. */
function leave() {
  if (window.history.state?.back) {
    router.back()
  } else {
    router.replace({ name: 'quotes' })
  }
}

/** Show 422 answers next to the fields they are about. Returns false when no field is to blame. */
function showFieldErrors(e: ApiError): boolean {
  let shown = false

  for (const field of fields) {
    const messages = e.fieldErrors[field]
    if (!messages?.length) continue

    const code = messages.find(isApiErrorCode)
    fieldErrors[field] = code && te(`apiErrors.${code}`) ? t(`apiErrors.${code}`) : t(`quotes.errors.${field}`)
    shown = true
  }

  return shown
}

async function submit() {
  saving.value = true
  error.value = ''
  for (const field of fields) delete fieldErrors[field]

  const body = {
    type: form.type,
    content: form.content.trim(),
    note: form.note.trim() || null,
    page: toPage(form.page),
  }

  try {
    if (quote.value) {
      await quotes.update(quote.value.id, body)
    } else if (bookId.value !== '') {
      await quotes.create(bookId.value, body)
    } else {
      return
    }

    leave()
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
  <PageHeader
    :title="editing ? $t('quotes.form.editTitle') : $t('quotes.form.createTitle')"
    :back-to="{ name: 'quotes' }"
  />

  <p v-if="notFound" class="py-10 text-center text-ink-muted">{{ $t('quotes.notFound') }}</p>

  <ErrorNotice v-else-if="loadError" :message="loadError" retryable @retry="load" />

  <p v-else-if="!ready" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <div
    v-else-if="!editing && !library.items.length"
    class="flex flex-col items-start gap-2 rounded-2xl border border-dashed border-line p-4"
  >
    <p class="text-ink-muted">{{ $t('quotes.form.noBooks') }}</p>
    <RouterLink
      :to="{ name: 'library' }"
      class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
    >
      {{ $t('quotes.form.toLibrary') }}
    </RouterLink>
  </div>

  <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
    <ErrorNotice v-if="error" :message="error" />

    <div v-if="quote" class="flex flex-col gap-1.5">
      <p class="text-sm font-medium">{{ $t('quotes.form.book') }}</p>
      <p class="min-h-11 content-center">{{ quote.book.title }}</p>
    </div>

    <div v-else class="flex flex-col gap-1.5">
      <label for="quote-book" class="text-sm font-medium">{{ $t('quotes.form.book') }}</label>
      <select
        id="quote-book"
        v-model="bookId"
        required
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
      >
        <option value="" disabled>{{ $t('quotes.form.bookPlaceholder') }}</option>
        <option v-for="userBook in library.byTitle" :key="userBook.id" :value="userBook.id">
          {{ userBook.book.title }}
        </option>
      </select>
    </div>

    <SegmentedControl v-model="form.type" :label="$t('quotes.type.label')" :options="typeOptions" />

    <div class="flex flex-col gap-1.5">
      <label for="quote-content" class="text-sm font-medium">{{ $t('quotes.form.content') }}</label>
      <textarea
        id="quote-content"
        v-model="form.content"
        required
        rows="6"
        :maxlength="MAX_LENGTH"
        :aria-invalid="fieldErrors.content ? 'true' : undefined"
        :aria-describedby="fieldErrors.content ? 'quote-content-error' : 'quote-content-counter'"
        class="rounded-lg border border-line bg-surface px-3 py-2 font-display text-base leading-relaxed outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
      />
      <p v-if="fieldErrors.content" id="quote-content-error" class="text-sm text-danger">{{ fieldErrors.content }}</p>
      <p id="quote-content-counter" class="text-right text-xs tabular-nums text-ink-muted">
        {{ $t('quotes.form.counter', { count: form.content.length, max: MAX_LENGTH }) }}
      </p>
    </div>

    <div class="flex flex-col gap-1.5">
      <label for="quote-note" class="text-sm font-medium">{{ $t('quotes.form.note') }}</label>
      <textarea
        id="quote-note"
        v-model="form.note"
        rows="3"
        :maxlength="MAX_LENGTH"
        :aria-invalid="fieldErrors.note ? 'true' : undefined"
        :aria-describedby="fieldErrors.note ? 'quote-note-error' : undefined"
        class="rounded-lg border border-line bg-surface px-3 py-2 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
      />
      <p v-if="fieldErrors.note" id="quote-note-error" class="text-sm text-danger">{{ fieldErrors.note }}</p>
    </div>

    <div class="flex flex-col gap-1.5">
      <label for="quote-page" class="text-sm font-medium">{{ $t('quotes.form.page') }}</label>
      <input
        id="quote-page"
        :value="form.page"
        type="number"
        inputmode="numeric"
        min="1"
        :max="maxPage"
        :aria-invalid="fieldErrors.page ? 'true' : undefined"
        :aria-describedby="fieldErrors.page ? 'quote-page-error' : undefined"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base tabular-nums outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
        @input="form.page = ($event.target as HTMLInputElement).value"
      >
      <p v-if="fieldErrors.page" id="quote-page-error" class="text-sm text-danger">{{ fieldErrors.page }}</p>
    </div>

    <div class="flex gap-2">
      <button
        type="submit"
        :disabled="saving"
        class="min-h-11 flex-1 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      >
        {{ $t('common.save') }}
      </button>
      <button
        type="button"
        class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium"
        @click="leave"
      >
        {{ $t('common.cancel') }}
      </button>
    </div>
  </form>
</template>
