<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { readerApi } from '@/api/reader'
import ErrorNotice from '@/components/ErrorNotice.vue'
import ProgressBar from '@/components/ProgressBar.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLibraryStore } from '@/stores/library'
import { useQuotesStore } from '@/stores/quotes'
import type { UserBook } from '@/types/api'

/** The server takes books up to 15 MB; a bigger file is turned away here, before it has been sent over a slow network. */
const MAX_FILE_BYTES = 15 * 1024 * 1024

const props = defineProps<{ userBook: UserBook }>()

const { t } = useI18n()
const library = useLibraryStore()
const quotes = useQuotesStore()
const errorMessage = useApiErrorMessage()

const picker = useTemplateRef<HTMLInputElement>('picker')
const uploading = ref(false)
const removing = ref(false)
const confirmingRemove = ref(false)
const error = ref('')

const percent = computed(() => Math.floor((props.userBook.text_progress ?? 0) * 100))
const started = computed(() => (props.userBook.text_progress ?? 0) > 0)

function choose() {
  picker.value?.click()
}

/** What to say about a failed upload: the code of the server, or that the file was too big for the proxy in front of it. */
function uploadError(failure: unknown): string {
  if (failure instanceof ApiError && failure.status === 413) {
    return t('apiErrors.reader.too_large')
  }

  return errorMessage(failure)
}

async function onFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  // So that choosing the same file again (after a failure) is a change too.
  input.value = ''

  if (!file) return

  error.value = ''

  if (file.size > MAX_FILE_BYTES) {
    error.value = t('apiErrors.reader.too_large')
    return
  }

  uploading.value = true

  try {
    const text = await readerApi.upload(props.userBook.id, file)
    library.upsert({ ...props.userBook, has_text: true, text_progress: text.progress })
    // A new text takes the places of the quotes in the old one with it.
    quotes.clearAnchors(props.userBook.id)
    confirmingRemove.value = false
  } catch (e) {
    error.value = uploadError(e)
  } finally {
    uploading.value = false
  }
}

async function removeText() {
  removing.value = true
  error.value = ''

  try {
    await readerApi.removeText(props.userBook.id)
    library.upsert({ ...props.userBook, has_text: false, text_progress: null })
    quotes.clearAnchors(props.userBook.id)
    confirmingRemove.value = false
  } catch (e) {
    // Somebody removed it already (another device): the text is gone either way.
    if (e instanceof ApiError && e.status === 404) {
      library.upsert({ ...props.userBook, has_text: false, text_progress: null })
      quotes.clearAnchors(props.userBook.id)
      confirmingRemove.value = false
    } else {
      error.value = errorMessage(e)
    }
  } finally {
    removing.value = false
  }
}
</script>

<template>
  <section class="flex flex-col gap-3 rounded-2xl border border-line bg-surface p-4" :aria-label="$t('reader.block.title')">
    <h2 class="font-display text-lg font-bold">{{ $t('reader.block.title') }}</h2>

    <ErrorNotice v-if="error" :message="error" />
    <p role="status" class="sr-only">{{ uploading ? $t('reader.block.uploading') : '' }}</p>

    <input
      ref="picker"
      type="file"
      accept=".epub,application/epub+zip"
      class="sr-only"
      tabindex="-1"
      :aria-label="$t('reader.block.inputLabel')"
      :disabled="uploading"
      @change="onFile"
    >

    <template v-if="!userBook.has_text">
      <p class="text-sm text-ink-muted">{{ $t('reader.block.hint') }}</p>

      <button
        type="button"
        :disabled="uploading"
        :aria-busy="uploading"
        class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
        @click="choose"
      >
        {{ uploading ? $t('reader.block.uploading') : $t('reader.block.upload') }}
      </button>

      <p class="text-xs text-ink-muted">{{ $t('reader.block.privacy') }}</p>
    </template>

    <template v-else>
      <div class="flex flex-col gap-1.5">
        <ProgressBar :percent="percent" :label="$t('reader.block.progressLabel')" />
        <p class="text-sm tabular-nums text-ink-muted">{{ $t('reader.block.percent', { percent }) }}</p>
      </div>

      <RouterLink
        :to="{ name: 'read', params: { id: userBook.id } }"
        class="inline-flex min-h-11 items-center justify-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
      >
        {{ started ? $t('reader.continue') : $t('reader.read') }}
      </RouterLink>

      <div v-if="!confirmingRemove" class="flex flex-wrap items-center gap-x-4">
        <button type="button" :disabled="uploading" :aria-busy="uploading" class="min-h-11 text-sm font-medium text-accent underline underline-offset-2 disabled:opacity-60" @click="choose">
          {{ uploading ? $t('reader.block.uploading') : $t('reader.block.replace') }}
        </button>
        <button type="button" :disabled="uploading" class="min-h-11 text-sm font-medium text-danger disabled:opacity-60" @click="confirmingRemove = true">
          {{ $t('reader.block.remove') }}
        </button>
      </div>
      <p v-if="!confirmingRemove" class="text-xs text-ink-muted">{{ $t('reader.block.replaceHint') }}</p>

      <div v-else class="flex flex-col gap-2">
        <p class="text-sm">{{ $t('reader.block.removeConfirm') }}</p>
        <div class="flex gap-2">
          <button
            type="button"
            :disabled="removing"
            class="min-h-11 flex-1 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
            @click="removeText"
          >
            {{ removing ? $t('reader.block.removing') : $t('reader.block.removeYes') }}
          </button>
          <button type="button" class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-4 font-medium" @click="confirmingRemove = false">
            {{ $t('common.cancel') }}
          </button>
        </div>
      </div>
    </template>
  </section>
</template>
