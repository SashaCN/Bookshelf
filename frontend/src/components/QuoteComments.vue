<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { socialApi } from '@/api/social'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLoadMore } from '@/composables/useLoadMore'
import { useAuthStore } from '@/stores/auth'
import { formatMoment } from '@/utils/format'
import type { QuoteComment } from '@/types/api'
import ErrorNotice from './ErrorNotice.vue'
import LoadMore from './LoadMore.vue'

const MAX_LENGTH = 1000

const props = defineProps<{ quoteId: number }>()
const emit = defineEmits<{ added: []; removed: [] }>()

const auth = useAuthStore()
const route = useRoute()
const { t } = useI18n()
const errorMessage = useApiErrorMessage()

const items = ref<QuoteComment[]>([])
const hasMore = ref(false)
const loaded = ref(false)
const loadingMore = ref(false)
const loadError = ref('')
let page = 1

const draft = ref('')
const sending = ref(false)
const sendError = ref('')
const confirmingId = ref<number | null>(null)
const removingId = ref<number | null>(null)
const removeError = ref('')

const COUNTER_FROM = 800

const canSend = computed(() => draft.value.trim() !== '' && draft.value.length <= MAX_LENGTH && !sending.value)
const rows = computed(() => Math.min(6, Math.max(2, draft.value.split('\n').length, Math.ceil(draft.value.length / 45))))
const loginTarget = computed(() => ({ name: 'login', query: { redirect: route.fullPath } }))

async function load() {
  loadError.value = ''

  try {
    const result = await socialApi.comments(props.quoteId, 1)
    items.value = result.data
    hasMore.value = result.links.next !== null
    page = 1
    loaded.value = true
  } catch (e) {
    loadError.value = errorMessage(e)
  }
}

const { moreError, loadMore } = useLoadMore(async () => {
  if (loadingMore.value) return

  loadingMore.value = true

  try {
    const result = await socialApi.comments(props.quoteId, page + 1)
    const known = new Set(items.value.map((comment) => comment.id))
    items.value = [...items.value, ...result.data.filter((comment) => !known.has(comment.id))]
    hasMore.value = result.links.next !== null
    page++
  } finally {
    loadingMore.value = false
  }
})

async function send() {
  if (!canSend.value) return

  sending.value = true
  sendError.value = ''

  try {
    const created = await socialApi.addComment(props.quoteId, draft.value.trim())
    items.value = [...items.value.filter((comment) => comment.id !== created.id), created]
    draft.value = ''
    emit('added')
  } catch (e) {
    sendError.value = errorMessage(e)
  } finally {
    sending.value = false
  }
}

async function remove(comment: QuoteComment) {
  removingId.value = comment.id
  removeError.value = ''

  try {
    await socialApi.deleteComment(props.quoteId, comment.id)
    items.value = items.value.filter((candidate) => candidate.id !== comment.id)
    emit('removed')
  } catch (e) {
    removeError.value = errorMessage(e)
  } finally {
    removingId.value = null
    confirmingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-3 border-t border-line pt-3" role="region" :aria-label="$t('quotes.comments.thread')">
    <ErrorNotice v-if="loadError" :message="loadError" retryable @retry="load" />
    <p v-else-if="!loaded" class="text-sm text-ink-muted">{{ $t('common.loading') }}</p>

    <template v-else>
      <p v-if="!items.length" class="text-sm text-ink-muted" data-test="comments-empty">{{ $t('quotes.comments.empty') }}</p>

      <ul v-else class="flex flex-col gap-3">
        <li v-for="comment in items" :key="comment.id" class="flex flex-col gap-1 rounded-lg bg-sunken px-3 py-2" data-test="comment">
          <div class="flex items-center justify-between gap-2">
            <p class="min-w-0 text-sm">
              <RouterLink
                v-if="comment.author.username"
                :to="{ name: 'public-profile', params: { username: comment.author.username } }"
                class="font-medium underline-offset-2 hover:underline"
              >
                {{ comment.author.name }}
              </RouterLink>
              <span v-else class="font-medium">{{ $t('quotes.comments.anonymous') }}</span>
              <span class="whitespace-nowrap text-ink-muted"> · {{ formatMoment(comment.created_at) }}</span>
            </p>
            <button
              v-if="comment.can_delete && confirmingId !== comment.id"
              type="button"
              class="-my-2 -mr-2 min-h-11 shrink-0 rounded-lg px-2 text-sm text-ink-muted hover:text-danger focus-visible:text-danger"
              @click="confirmingId = comment.id"
            >
              {{ $t('quotes.comments.remove') }}
            </button>
          </div>
          <p class="whitespace-pre-line break-words text-sm" data-test="comment-body">{{ comment.body }}</p>

          <div v-if="comment.can_delete && confirmingId === comment.id" class="flex flex-col gap-2">
            <p class="text-sm">{{ $t('quotes.comments.removeConfirm') }}</p>
            <div class="flex gap-2">
              <button
                type="button"
                :disabled="removingId === comment.id"
                class="min-h-11 flex-1 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
                @click="remove(comment)"
              >
                {{ $t('quotes.comments.removeYes') }}
              </button>
              <button
                type="button"
                class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-4 font-medium"
                @click="confirmingId = null"
              >
                {{ $t('common.cancel') }}
              </button>
            </div>
          </div>
        </li>
      </ul>

      <ErrorNotice v-if="removeError" :message="removeError" />

      <LoadMore :has-more="hasMore" :loading="loadingMore" :error="moreError" class="!mt-0" @more="loadMore" />

      <form v-if="auth.isAuthenticated" class="flex flex-col gap-2" @submit.prevent="send">
        <label :for="`comment-${quoteId}`" class="text-sm font-medium">{{ $t('quotes.comments.label') }}</label>
        <textarea
          :id="`comment-${quoteId}`"
          v-model="draft"
          :rows="rows"
          :maxlength="MAX_LENGTH"
          :placeholder="$t('quotes.comments.placeholder')"
          class="w-full resize-none rounded-lg border border-line bg-surface px-3 py-2 [field-sizing:content]"
        />
        <div class="flex items-center justify-between gap-3">
          <span v-if="draft.length >= COUNTER_FROM" class="text-xs text-ink-muted" data-test="comment-counter">
            {{ t('quotes.comments.counter', { n: draft.length, max: MAX_LENGTH }) }}
          </span>
          <button
            type="submit"
            :disabled="!canSend"
            class="ml-auto min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
          >
            {{ sending ? $t('quotes.comments.sending') : $t('quotes.comments.send') }}
          </button>
        </div>
        <ErrorNotice v-if="sendError" :message="sendError" />
      </form>
      <RouterLink v-else :to="loginTarget" class="inline-flex min-h-11 items-center text-sm font-medium text-accent underline underline-offset-2">
        {{ $t('quotes.comments.login') }}
      </RouterLink>
    </template>
  </div>
</template>
