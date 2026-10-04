<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import SegmentedControl from '@/components/SegmentedControl.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useQuotesStore } from '@/stores/quotes'
import type { Quote } from '@/types/api'
import { CARD_HEIGHT, CARD_WIDTH, drawQuoteCard } from '@/utils/quoteCard'
import type { CardTemplate } from '@/utils/quoteCard'

/** Revoking the link at once can cancel the download in some browsers. */
const REVOKE_DELAY_MS = 10_000

const props = defineProps<{ id: number }>()

const { t } = useI18n()
const quotes = useQuotesStore()
const errorMessage = useApiErrorMessage()

const quote = ref<Quote | null>(null)
const notFound = ref(false)
const loadError = ref('')
const template = ref<CardTemplate>('light')
const sharing = ref(false)
const shareError = ref('')
const canvas = useTemplateRef<HTMLCanvasElement>('canvas')

const templateOptions = computed(() => [
  { value: 'light' as const, label: t('quotes.shareCard.light') },
  { value: 'dark' as const, label: t('quotes.shareCard.dark') },
  { value: 'accent' as const, label: t('quotes.shareCard.accent') },
])

async function load() {
  notFound.value = false
  loadError.value = ''

  try {
    quote.value = await quotes.get(props.id)
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

function draw() {
  const context = canvas.value?.getContext('2d')
  if (!context || !quote.value) return

  drawQuoteCard(
    context,
    {
      text: quote.value.content,
      title: quote.value.book.title,
      author: quote.value.book.authors.join(', '),
      pageLabel: quote.value.page ? t('quotes.page', { page: quote.value.page }) : null,
      brand: t('app.name'),
    },
    template.value,
  )
}

// The canvas only exists once the quote is loaded; a changed quote or template paints it again.
watch([quote, template, canvas], draw, { flush: 'post' })

function toBlob(element: HTMLCanvasElement): Promise<Blob> {
  return new Promise((resolve, reject) => {
    element.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('The canvas could not be turned into an image'))), 'image/png')
  })
}

function download(file: File) {
  const url = URL.createObjectURL(file)
  const link = document.createElement('a')
  link.href = url
  link.download = file.name
  document.body.append(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), REVOKE_DELAY_MS)
}

/** The share sheet where the browser can share files (phones), a download elsewhere (desktop). */
async function share() {
  if (!canvas.value || !quote.value) return

  sharing.value = true
  shareError.value = ''

  try {
    const file = new File([await toBlob(canvas.value)], `bookshelf-quote-${quote.value.id}.png`, { type: 'image/png' })

    if (navigator.canShare?.({ files: [file] })) {
      await navigator.share({ files: [file], title: quote.value.book.title })
    } else {
      download(file)
    }
  } catch (e) {
    // Closing the share sheet is the reader's choice, not a failure.
    if (!(e instanceof DOMException && e.name === 'AbortError')) {
      shareError.value = t('quotes.shareCard.failed')
    }
  } finally {
    sharing.value = false
  }
}
</script>

<template>
  <PageHeader :title="$t('quotes.shareCard.title')" :back-to="{ name: 'quotes' }" />

  <p v-if="notFound" class="py-10 text-center text-ink-muted">{{ $t('quotes.notFound') }}</p>

  <ErrorNotice v-else-if="loadError" :message="loadError" retryable @retry="load" />

  <p v-else-if="!quote" class="py-10 text-center text-ink-muted">{{ $t('common.loading') }}</p>

  <div v-else class="flex flex-col gap-4">
    <SegmentedControl v-model="template" :label="$t('quotes.shareCard.template')" :options="templateOptions" />

    <canvas
      ref="canvas"
      :width="CARD_WIDTH"
      :height="CARD_HEIGHT"
      role="img"
      :aria-label="$t('quotes.shareCard.preview')"
      class="h-auto w-full rounded-xl border border-line shadow-sm"
    >
      {{ quote.content }}
    </canvas>

    <ErrorNotice v-if="shareError" :message="shareError" />

    <button
      type="button"
      :disabled="sharing"
      class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      @click="share"
    >
      {{ $t('quotes.share') }}
    </button>
  </div>
</template>
