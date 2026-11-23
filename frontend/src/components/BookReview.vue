<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ErrorNotice from '@/components/ErrorNotice.vue'
import SegmentedControl from '@/components/SegmentedControl.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useAuthStore } from '@/stores/auth'
import { useLibraryStore } from '@/stores/library'
import type { QuoteVisibility, UserBook } from '@/types/api'

/** Keep in step with the server's limit. */
const MAX_LENGTH = 5000

const props = defineProps<{ userBook: UserBook }>()

const { t } = useI18n()
const library = useLibraryStore()
const auth = useAuthStore()
const errorMessage = useApiErrorMessage()

const editing = ref(props.userBook.review === null)
const text = ref(props.userBook.review ?? '')
const visibility = ref<QuoteVisibility>(props.userBook.review_visibility)
const busy = ref(false)
const error = ref('')

const visibilityOptions = computed(() => [
  { value: 'private' as const, label: t('book.review.private') },
  { value: 'public' as const, label: t('book.review.public') },
])

const unchanged = computed(
  () => text.value.trim() === (props.userBook.review ?? '') && visibility.value === props.userBook.review_visibility,
)

function startEditing() {
  text.value = props.userBook.review ?? ''
  visibility.value = props.userBook.review_visibility
  error.value = ''
  editing.value = true
}

function cancel() {
  error.value = ''
  editing.value = false
}

async function save() {
  if (busy.value) return

  busy.value = true
  error.value = ''

  try {
    const saved = await library.update(props.userBook.id, { review: text.value.trim(), review_visibility: visibility.value })
    text.value = saved.review ?? ''
    editing.value = saved.review === null
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="flex flex-col gap-3" aria-labelledby="book-review-title">
    <h2 id="book-review-title" class="font-display text-xl font-semibold">{{ $t('book.review.title') }}</h2>

    <template v-if="!editing && userBook.review">
      <p class="whitespace-pre-line break-words">{{ userBook.review }}</p>
      <p class="text-sm text-ink-muted">{{ $t(`book.review.${userBook.review_visibility}`) }}</p>
      <button type="button" class="min-h-11 self-start rounded-lg border border-line bg-surface px-4 font-medium" @click="startEditing">
        {{ $t('book.review.edit') }}
      </button>
    </template>

    <form v-else class="flex flex-col gap-3" @submit.prevent="save">
      <ErrorNotice v-if="error" :message="error" />

      <div class="flex flex-col gap-1.5">
        <label for="book-review" class="text-sm font-medium">{{ $t('book.review.label') }}</label>
        <textarea
          id="book-review"
          v-model="text"
          rows="6"
          :maxlength="MAX_LENGTH"
          :placeholder="$t('book.review.placeholder')"
          aria-describedby="book-review-counter"
          class="min-h-32 rounded-lg border border-line bg-surface px-3 py-2 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
        />
        <p id="book-review-counter" class="text-right text-sm tabular-nums text-ink-muted">
          {{ $t('book.review.counter', { count: text.length, max: MAX_LENGTH }) }}
        </p>
      </div>

      <SegmentedControl v-model="visibility" :label="$t('book.review.visibility')" :options="visibilityOptions" />
      <p class="text-sm text-ink-muted">{{ $t('book.review.hint') }}</p>
      <p v-if="visibility === 'public' && auth.user && !auth.user.is_public" role="status" class="text-sm text-danger">
        {{ $t('book.review.profilePrivate') }}
        <RouterLink :to="{ name: 'profile' }" class="font-medium underline underline-offset-2">{{ $t('book.review.toProfile') }}</RouterLink>
      </p>
      <p v-if="userBook.review !== null" class="text-sm text-ink-muted">{{ $t('book.review.clearHint') }}</p>

      <div class="flex gap-2">
        <button
          type="submit"
          :disabled="busy || unchanged"
          class="min-h-11 flex-1 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
        >
          {{ busy ? $t('book.review.saving') : $t('book.review.save') }}
        </button>
        <button
          v-if="userBook.review !== null"
          type="button"
          :disabled="busy"
          class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
          @click="cancel"
        >
          {{ $t('common.cancel') }}
        </button>
      </div>
    </form>
  </section>
</template>
