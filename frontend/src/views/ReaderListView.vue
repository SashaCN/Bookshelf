<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import ProgressBar from '@/components/ProgressBar.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLibraryStore } from '@/stores/library'
import { useReaderStore } from '@/stores/reader'

const MAX_FILE_BYTES = 20 * 1024 * 1024

const { t } = useI18n()
const reader = useReaderStore()
const library = useLibraryStore()
const errorMessage = useApiErrorMessage()

const error = ref('')
const uploadError = ref('')
const uploading = ref(false)
const confirmingId = ref<number | null>(null)
const removingId = ref<number | null>(null)
const linkingId = ref<number | null>(null)
const input = ref<HTMLInputElement | null>(null)

async function load() {
  error.value = ''
  try {
    await reader.load()
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function onFile(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  uploadError.value = ''

  // The server turns such a file down too; this only spares the reader a long upload.
  if (file.size > MAX_FILE_BYTES) {
    uploadError.value = t('apiErrors.reader.too_large')
    target.value = ''
    return
  }

  uploading.value = true
  try {
    await reader.upload(file)
  } catch (e) {
    uploadError.value = errorMessage(e)
  } finally {
    uploading.value = false
    target.value = ''
  }
}

async function remove(id: number) {
  uploadError.value = ''
  removingId.value = id
  try {
    await reader.remove(id)
  } catch (e) {
    uploadError.value = errorMessage(e)
  } finally {
    removingId.value = null
    confirmingId.value = null
  }
}

async function link(id: number, event: Event) {
  const value = (event.target as HTMLSelectElement).value
  uploadError.value = ''
  linkingId.value = id
  try {
    await reader.link(id, value === '' ? null : Number(value))
  } catch (e) {
    uploadError.value = errorMessage(e)
  } finally {
    linkingId.value = null
  }
}

function pick() {
  input.value?.click()
}

// The library only feeds the pickers; the list is usable without it.
async function loadLibrary() {
  try {
    await library.load()
  } catch {
    // The picker stays empty; the reader can retry by reopening the page.
  }
}

onMounted(() => {
  void load()
  void loadLibrary()
})
</script>

<template>
  <PageHeader :title="$t('reader.title')" :back-to="{ name: 'home' }" />

  <input
    ref="input"
    type="file"
    accept=".epub,application/epub+zip"
    class="hidden"
    data-testid="epub-input"
    @change="onFile"
  >

  <button
    v-if="reader.loaded && !error && reader.items.length"
    type="button"
    :disabled="uploading"
    class="mb-4 flex min-h-11 w-full items-center justify-center rounded-2xl border border-dashed border-line bg-surface px-4 font-semibold text-accent disabled:opacity-60"
    @click="pick"
  >
    {{ uploading ? $t('reader.uploading') : $t('reader.upload') }}
  </button>

  <p v-if="uploading" role="status" class="mb-4 text-sm text-ink-muted">{{ $t('reader.uploading') }}</p>
  <ErrorNotice v-if="uploadError" :message="uploadError" class="mb-4" />
  <ErrorNotice v-if="error" :message="error" retryable @retry="load" />

  <p v-else-if="!reader.loaded" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <template v-else>
    <ul v-if="reader.items.length" class="flex flex-col gap-3">
      <li v-for="doc in reader.items" :key="doc.id" class="rounded-2xl border border-line bg-surface p-4">
        <RouterLink :to="{ name: 'reader-view', params: { id: doc.id } }" class="block">
          <p class="font-display text-lg font-semibold">{{ doc.title }}</p>
          <p v-if="doc.author" class="text-sm text-ink-muted">{{ doc.author }}</p>
        </RouterLink>

        <div class="mt-3 flex items-center gap-3">
          <div class="flex-1">
            <ProgressBar :percent="doc.progress" :label="$t('reader.progress')" />
          </div>
          <span class="text-sm tabular-nums text-ink-muted">{{ Math.round(doc.progress) }}%</span>
        </div>
        <p class="mt-1 text-sm text-ink-muted">
          {{ $t('reader.chapter', { n: Math.min(doc.position.chapter + 1, doc.chapters_count), total: doc.chapters_count }) }}
        </p>

        <div class="mt-3 flex flex-col gap-1">
          <p v-if="doc.user_book" class="text-sm">
            {{ $t('reader.link.linkedTo') }}
            <RouterLink :to="{ name: 'book', params: { id: doc.user_book.id } }" class="font-medium text-accent underline">
              {{ doc.user_book.title }}
            </RouterLink>
          </p>
          <label :for="`link-${doc.id}`" class="text-sm text-ink-muted">{{ $t('reader.link.label') }}</label>
          <select
            :id="`link-${doc.id}`"
            :value="doc.user_book_id ?? ''"
            :disabled="linkingId === doc.id"
            class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base disabled:opacity-60"
            @change="link(doc.id, $event)"
          >
            <option value="">{{ $t('reader.link.none') }}</option>
            <option v-for="item in library.byTitle" :key="item.id" :value="item.id">{{ item.book.title }}</option>
          </select>
        </div>

        <div class="mt-3 flex items-center gap-2">
          <template v-if="confirmingId === doc.id">
            <span class="mr-auto text-sm">{{ $t('reader.confirmRemove') }}</span>
            <button type="button" :disabled="removingId === doc.id" class="min-h-11 rounded-lg border border-line px-3 text-sm disabled:opacity-60" @click="confirmingId = null">
              {{ $t('common.cancel') }}
            </button>
            <button type="button" :disabled="removingId === doc.id" class="min-h-11 rounded-lg bg-danger px-3 text-sm font-semibold text-on-accent disabled:opacity-60" @click="remove(doc.id)">
              {{ removingId === doc.id ? $t('reader.removing') : $t('reader.confirmYes') }}
            </button>
          </template>
          <template v-else>
            <RouterLink
              :to="{ name: 'reader-view', params: { id: doc.id } }"
              class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
            >
              {{ doc.progress > 0 ? $t('reader.continue') : $t('reader.read') }}
            </RouterLink>
            <button type="button" :aria-label="$t('reader.removeNamed', { title: doc.title })" class="min-h-11 rounded-lg px-3 text-sm text-ink-muted" @click="confirmingId = doc.id">
              {{ $t('reader.remove') }}
            </button>
          </template>
        </div>
      </li>
    </ul>

    <EmptyState v-else :title="$t('reader.empty.title')" :text="$t('reader.empty.text')">
      <button
        type="button"
        :disabled="uploading"
        class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
        @click="pick"
      >
        {{ uploading ? $t('reader.uploading') : $t('reader.upload') }}
      </button>
    </EmptyState>
  </template>
</template>
