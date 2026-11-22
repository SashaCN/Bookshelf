<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '@/components/EmptyState.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import ProgressBar from '@/components/ProgressBar.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useReaderStore } from '@/stores/reader'

const MAX_FILE_BYTES = 20 * 1024 * 1024

const { t } = useI18n()
const reader = useReaderStore()
const errorMessage = useApiErrorMessage()

const error = ref('')
const uploadError = ref('')
const uploading = ref(false)
const confirmingId = ref<number | null>(null)
const removingId = ref<number | null>(null)
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

function pick() {
  input.value?.click()
}

onMounted(load)
</script>

<template>
  <PageHeader :title="$t('reader.title')" :back-to="{ name: 'home' }">
    <template #action>
      <button
        type="button"
        :disabled="uploading"
        class="inline-flex min-h-10 items-center rounded-lg bg-accent px-3 text-sm font-semibold text-on-accent disabled:opacity-60"
        @click="pick"
      >
        {{ uploading ? $t('reader.uploading') : $t('reader.upload') }}
      </button>
    </template>
  </PageHeader>

  <input
    ref="input"
    type="file"
    accept=".epub,application/epub+zip"
    class="hidden"
    data-testid="epub-input"
    @change="onFile"
  >

  <p v-if="uploading" role="status" class="mb-4 text-sm text-ink-muted">{{ $t('reader.uploading') }}</p>
  <ErrorNotice v-if="uploadError" :message="uploadError" class="mb-4" />
  <ErrorNotice v-if="error" :message="error" retryable @retry="load" />

  <p v-else-if="!reader.loaded" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <template v-else>
    <ul v-if="reader.items.length" class="flex flex-col gap-3">
      <li v-for="doc in reader.items" :key="doc.id" class="rounded-2xl border border-line bg-surface p-4">
        <RouterLink :to="{ name: 'reader-view', params: { id: doc.id } }" class="block">
          <p class="font-display text-lg font-semibold">{{ doc.title }}</p>
          <p v-if="doc.author" class="text-sm text-ink-muted">{{ $t('reader.author', { name: doc.author }) }}</p>
        </RouterLink>

        <div class="mt-3 flex items-center gap-3">
          <div class="flex-1">
            <ProgressBar :percent="doc.progress" :label="$t('reader.progress')" />
          </div>
          <span class="text-sm tabular-nums text-ink-muted">{{ Math.round(doc.progress) }}%</span>
        </div>

        <div class="mt-3 flex items-center justify-end gap-2">
          <template v-if="confirmingId === doc.id">
            <span class="mr-auto text-sm">{{ $t('reader.confirmRemove') }}</span>
            <button type="button" :disabled="removingId === doc.id" class="min-h-11 rounded-lg border border-line px-3 text-sm disabled:opacity-60" @click="confirmingId = null">
              {{ $t('common.cancel') }}
            </button>
            <button type="button" :disabled="removingId === doc.id" class="min-h-11 rounded-lg bg-danger px-3 text-sm font-semibold text-on-accent disabled:opacity-60" @click="remove(doc.id)">
              {{ removingId === doc.id ? $t('reader.removing') : $t('reader.confirmYes') }}
            </button>
          </template>
          <button v-else type="button" :aria-label="$t('reader.removeNamed', { title: doc.title })" class="min-h-11 rounded-lg px-3 text-sm text-danger" @click="confirmingId = doc.id">
            {{ $t('reader.remove') }}
          </button>
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
