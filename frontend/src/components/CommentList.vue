<script setup lang="ts">
import { ref } from 'vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useCommentsStore } from '@/stores/comments'
import type { Comment } from '@/types/social'
import { formatPostDate } from '@/utils/social'
import ErrorNotice from './ErrorNotice.vue'
import ReaderAvatar from './ReaderAvatar.vue'
import ReportDialog from './ReportDialog.vue'

defineProps<{ comments: Comment[] }>()

const store = useCommentsStore()
const errorMessage = useApiErrorMessage()

const removingId = ref<number | null>(null)
const reportingId = ref<number | null>(null)
const busy = ref(false)
const error = ref('')

async function remove(comment: Comment) {
  busy.value = true
  error.value = ''

  try {
    await store.remove(comment)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
    removingId.value = null
  }
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <ErrorNotice v-if="error" :message="error" />

    <ul class="flex flex-col gap-3">
      <li v-for="comment in comments" :key="comment.id" class="flex gap-3 rounded-2xl border border-line bg-surface p-3">
        <ReaderAvatar :name="comment.author.name" />

        <div class="flex min-w-0 flex-1 flex-col gap-1">
          <p class="truncate text-sm">
            <RouterLink
              :to="{ name: 'reader', params: { handle: comment.author.handle } }"
              class="font-semibold underline-offset-2 hover:underline"
            >
              {{ comment.author.name }}
            </RouterLink>
            <span class="text-ink-muted"> · {{ formatPostDate(comment.created_at) }}</span>
          </p>

          <p v-if="comment.is_hidden" role="status" class="self-start rounded-full bg-danger-soft px-2.5 py-0.5 text-xs font-medium text-danger">
            {{ $t('social.comments.hidden') }}
          </p>

          <p class="whitespace-pre-line break-words">{{ comment.content }}</p>

          <div v-if="removingId === comment.id" class="mt-1 flex flex-col gap-2">
            <p class="text-sm">{{ $t('social.comments.removeConfirm') }}</p>
            <div class="flex gap-2">
              <button
                type="button"
                :disabled="busy"
                class="min-h-11 flex-1 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
                @click="remove(comment)"
              >
                {{ $t('social.comments.removeYes') }}
              </button>
              <button
                type="button"
                class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-4 font-medium"
                @click="removingId = null"
              >
                {{ $t('common.cancel') }}
              </button>
            </div>
          </div>

          <div v-else-if="comment.can_delete || !comment.is_mine" class="-mb-1 flex gap-2">
            <button
              v-if="!comment.is_mine"
              type="button"
              class="min-h-11 text-sm font-medium text-ink-muted"
              @click="reportingId = comment.id"
            >
              {{ $t('social.comments.report') }}
            </button>
            <button
              v-if="comment.can_delete"
              type="button"
              class="min-h-11 text-sm font-medium text-danger"
              @click="removingId = comment.id"
            >
              {{ $t('social.comments.remove') }}
            </button>
          </div>
        </div>
      </li>
    </ul>

    <ReportDialog v-if="reportingId !== null" :target="{ kind: 'comment', id: reportingId }" @close="reportingId = null" />
  </div>
</template>
