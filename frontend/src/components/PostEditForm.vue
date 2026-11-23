<script setup lang="ts">
import { reactive, ref } from 'vue'
import { ApiError } from '@/api/http'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { usePostsStore } from '@/stores/posts'
import type { Post, UpdatePostPayload } from '@/types/social'
import ErrorNotice from './ErrorNotice.vue'
import RatingStars from './RatingStars.vue'

const COMMENT_MAX = 2000
const CONTENT_MAX = 5000

const props = defineProps<{ post: Post }>()
const emit = defineEmits<{ done: [] }>()

const posts = usePostsStore()
const errorMessage = useApiErrorMessage()

// The form starts from the post as it is when editing begins.
const draft = reactive({
  comment: props.post.comment ?? '',
  rating: props.post.rating as number | null,
  content: props.post.content ?? '',
})
const saving = ref(false)
const error = ref('')
/** The field the server found fault with, or the one that is empty. */
const invalid = ref<'comment' | 'rating' | 'content' | ''>('')

async function save() {
  error.value = ''
  invalid.value = ''

  let payload: UpdatePostPayload

  if (props.post.type === 'quote') {
    payload = { comment: draft.comment.trim() || null }
  } else {
    if (draft.rating === null) {
      invalid.value = 'rating'
      return
    }

    payload = { rating: draft.rating, content: draft.content.trim() || null }
  }

  saving.value = true

  try {
    await posts.update(props.post.id, payload)
    emit('done')
  } catch (e) {
    const field = e instanceof ApiError && e.status === 422 ? Object.keys(e.fieldErrors)[0] : undefined

    if (field === 'comment' || field === 'rating' || field === 'content') {
      invalid.value = field
    } else {
      error.value = errorMessage(e)
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="flex flex-col gap-4 rounded-2xl border border-line bg-surface p-4" @submit.prevent="save">
    <h2 class="font-display text-xl font-semibold">{{ $t('social.edit.title') }}</h2>
    <ErrorNotice v-if="error" :message="error" />

    <template v-if="post.type === 'quote'">
      <p class="text-sm text-ink-muted">{{ $t('social.edit.snapshot') }}</p>
      <div class="flex flex-col gap-1.5">
        <label for="edit-comment" class="text-sm font-medium">{{ $t('social.compose.comment') }}</label>
        <textarea
          id="edit-comment"
          v-model="draft.comment"
          rows="4"
          :maxlength="COMMENT_MAX"
          :aria-invalid="invalid === 'comment' ? 'true' : undefined"
          :aria-describedby="invalid === 'comment' ? 'edit-comment-error' : undefined"
          class="rounded-lg border border-line bg-surface px-3 py-2 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
        />
        <p v-if="invalid === 'comment'" id="edit-comment-error" class="text-sm text-danger">{{ $t('social.compose.errors.comment') }}</p>
        <p class="text-right text-xs tabular-nums text-ink-muted">
          {{ $t('social.compose.counter', { count: draft.comment.length, max: COMMENT_MAX }) }}
        </p>
      </div>
    </template>

    <template v-else>
      <div class="flex flex-col gap-1">
        <p class="text-sm font-medium">{{ $t('social.compose.rating') }}</p>
        <RatingStars v-model="draft.rating" :label="$t('social.compose.rating')" />
        <p v-if="invalid === 'rating'" role="alert" class="text-sm text-danger">{{ $t('social.compose.errors.rating') }}</p>
      </div>
      <div class="flex flex-col gap-1.5">
        <label for="edit-content" class="text-sm font-medium">{{ $t('social.compose.content') }}</label>
        <textarea
          id="edit-content"
          v-model="draft.content"
          rows="6"
          :maxlength="CONTENT_MAX"
          :aria-invalid="invalid === 'content' ? 'true' : undefined"
          :aria-describedby="invalid === 'content' ? 'edit-content-error' : undefined"
          class="rounded-lg border border-line bg-surface px-3 py-2 text-base leading-relaxed outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
        />
        <p v-if="invalid === 'content'" id="edit-content-error" class="text-sm text-danger">{{ $t('social.compose.errors.content') }}</p>
        <p class="text-right text-xs tabular-nums text-ink-muted">
          {{ $t('social.compose.counter', { count: draft.content.length, max: CONTENT_MAX }) }}
        </p>
      </div>
    </template>

    <div class="flex gap-2">
      <button
        type="submit"
        :disabled="saving"
        class="min-h-11 flex-1 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      >
        {{ $t('common.save') }}
      </button>
      <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium" @click="emit('done')">
        {{ $t('common.cancel') }}
      </button>
    </div>
  </form>
</template>
