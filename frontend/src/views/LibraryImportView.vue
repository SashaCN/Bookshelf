<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import { libraryApi } from '@/api/library'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLibraryStore } from '@/stores/library'
import type { GoodreadsImportResult } from '@/types/api'

const MAX_FILE_BYTES = 2 * 1024 * 1024

const { t, te } = useI18n()
const library = useLibraryStore()
const errorMessage = useApiErrorMessage()

const file = ref<File | null>(null)
const input = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const error = ref('')
const result = ref<GoodreadsImportResult | null>(null)

const steps = ['step1', 'step2', 'step3', 'step4']

const reasonText = computed(() => (reason: string) => (te(`libraryImport.reasons.${reason}`) ? t(`libraryImport.reasons.${reason}`) : t('libraryImport.reasons.unknown')))

function onFile(event: Event) {
  const picked = (event.target as HTMLInputElement).files?.[0] ?? null
  error.value = ''

  // The server turns such a file down too; this only spares the reader a long upload.
  if (picked && picked.size > MAX_FILE_BYTES) {
    error.value = t('libraryImport.tooLarge')
    file.value = null
    if (input.value) input.value.value = ''
    return
  }

  file.value = picked
}

async function submit() {
  if (!file.value || uploading.value) return

  error.value = ''
  uploading.value = true
  try {
    result.value = await libraryApi.importGoodreads(file.value)
    // The library list now misses the new books.
    try {
      await library.load(true)
    } catch {
      // The library screen reloads on its own and shows the failure there.
    }
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    uploading.value = false
  }
}

function reset() {
  result.value = null
  file.value = null
  error.value = ''
}
</script>

<template>
  <PageHeader :title="$t('libraryImport.title')" :back-to="{ name: 'library' }" />

  <section v-if="result" class="flex flex-col gap-4" data-testid="import-result">
    <h2 class="font-display text-lg font-semibold">{{ $t('libraryImport.result.title') }}</h2>

    <ul class="flex flex-col gap-1">
      <li>{{ $t('libraryImport.result.imported', { n: result.imported }, result.imported) }}</li>
      <li>{{ $t('libraryImport.result.existing', { n: result.skipped_existing }, result.skipped_existing) }}</li>
      <li v-if="result.skipped_invalid">{{ $t('libraryImport.result.invalid', { n: result.skipped_invalid }, result.skipped_invalid) }}</li>
    </ul>

    <div v-if="result.errors.length" class="rounded-2xl border border-line bg-surface p-4">
      <h3 class="font-semibold">{{ $t('libraryImport.result.errorsTitle') }}</h3>
      <ul class="mt-2 flex flex-col gap-1 text-sm text-ink-muted">
        <li v-for="item in result.errors" :key="item.row">
          {{ $t('libraryImport.result.row', { row: item.row, reason: reasonText(item.reason) }) }}
        </li>
      </ul>
      <p v-if="result.skipped_invalid > result.errors.length" class="mt-2 text-sm text-ink-muted">
        {{ $t('libraryImport.result.more', { n: result.errors.length }) }}
      </p>
    </div>

    <RouterLink :to="{ name: 'library' }" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-accent px-4 font-semibold text-on-accent">
      {{ $t('libraryImport.result.back') }}
    </RouterLink>
    <button type="button" class="min-h-11 rounded-lg px-4 text-sm text-ink-muted" @click="reset">
      {{ $t('libraryImport.result.again') }}
    </button>
  </section>

  <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
    <p>{{ $t('libraryImport.intro') }}</p>

    <div class="rounded-2xl border border-line bg-surface p-4">
      <h2 class="font-semibold">{{ $t('libraryImport.howTo.title') }}</h2>
      <ol class="mt-2 flex list-decimal flex-col gap-1 pl-5 text-sm text-ink-muted">
        <li v-for="step in steps" :key="step">{{ $t(`libraryImport.howTo.${step}`) }}</li>
      </ol>
    </div>

    <p class="text-sm text-ink-muted">{{ $t('libraryImport.rules') }}</p>

    <div class="flex flex-col gap-1">
      <label for="goodreads-file" class="text-sm font-medium">{{ $t('libraryImport.file') }}</label>
      <input
        id="goodreads-file"
        ref="input"
        type="file"
        accept=".csv,text/csv"
        :disabled="uploading"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 py-2 text-base"
        @change="onFile"
      >
    </div>

    <ErrorNotice v-if="error" :message="error" />
    <p v-if="uploading" role="status" class="text-sm text-ink-muted">{{ $t('libraryImport.uploading') }}</p>

    <button
      type="submit"
      :disabled="!file || uploading"
      class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
    >
      {{ uploading ? $t('libraryImport.uploading') : $t('libraryImport.submit') }}
    </button>
  </form>
</template>
