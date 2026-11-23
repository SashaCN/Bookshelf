<script setup lang="ts">
import { computed, ref } from 'vue'
import { ApiError } from '@/api/http'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useCommentsStore } from '@/stores/comments'
import ErrorNotice from './ErrorNotice.vue'

const MAX_LENGTH = 1000

const props = defineProps<{ postId: number }>()

const comments = useCommentsStore()
const errorMessage = useApiErrorMessage()

const content = ref('')
const sending = ref(false)
const error = ref('')
const invalid = ref(false)

const empty = computed(() => content.value.trim() === '')

async function submit() {
  if (empty.value || sending.value) return

  sending.value = true
  error.value = ''
  invalid.value = false

  try {
    await comments.add(props.postId, content.value.trim())
    content.value = ''
  } catch (e) {
    if (e instanceof ApiError && e.status === 422 && e.fieldErrors.content?.length) {
      invalid.value = true
    } else {
      error.value = errorMessage(e)
    }
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <form class="flex flex-col gap-2" @submit.prevent="submit">
    <ErrorNotice v-if="error" :message="error" />

    <label for="comment-content" class="text-sm font-medium">{{ $t('social.comments.label') }}</label>
    <textarea
      id="comment-content"
      v-model="content"
      rows="3"
      :maxlength="MAX_LENGTH"
      :placeholder="$t('social.comments.placeholder')"
      :aria-invalid="invalid ? 'true' : undefined"
      :aria-describedby="invalid ? 'comment-content-error' : 'comment-content-counter'"
      class="rounded-lg border border-line bg-surface px-3 py-2 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
    />
    <p v-if="invalid" id="comment-content-error" class="text-sm text-danger">{{ $t('social.comments.errors.content') }}</p>
    <div class="flex items-center justify-between gap-3">
      <p id="comment-content-counter" class="text-xs tabular-nums text-ink-muted">
        {{ $t('social.comments.counter', { count: content.length, max: MAX_LENGTH }) }}
      </p>
      <button
        type="submit"
        :disabled="sending || empty"
        class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      >
        {{ sending ? $t('social.comments.sending') : $t('social.comments.submit') }}
      </button>
    </div>
  </form>
</template>
