<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useLibraryStore } from '@/stores/library'
import { usePostsStore } from '@/stores/posts'
import type { Post } from '@/types/social'
import { formatPostDate } from '@/utils/social'
import ErrorNotice from './ErrorNotice.vue'
import RatingStars from './RatingStars.vue'
import ReaderAvatar from './ReaderAvatar.vue'
import ReportDialog from './ReportDialog.vue'

const props = withDefaults(
  defineProps<{
    post: Post
    /** On the page of the post itself: the whole text, and no link to the page. */
    detail?: boolean
    /** Leave the book out where the screen is already about it. */
    showBook?: boolean
  }>(),
  { detail: false, showBook: true },
)

const { t } = useI18n()
const posts = usePostsStore()
const library = useLibraryStore()
const errorMessage = useApiErrorMessage()

const error = ref('')
const menuOpen = ref(false)
const confirmingRemove = ref(false)
const reporting = ref(false)
const busy = ref(false)

const menuId = useId()
const menuButton = ref<HTMLButtonElement | null>(null)
const menuPanel = ref<HTMLElement | null>(null)

/** The book page is the reader's own library entry, so the link exists only for books they have. */
const libraryEntry = computed(() => library.items.find((item) => item.book.id === props.post.book.id))
const authors = computed(() => props.post.book.authors.join(', '))
const date = computed(() => formatPostDate(props.post.created_at))
const ratingLabel = computed(() => t('social.post.rating', { rating: props.post.rating ?? 0 }))

async function toggleLike() {
  error.value = ''

  try {
    await posts.toggleLike(props.post.id)
  } catch (e) {
    error.value = errorMessage(e)
  }
}

function closeMenu(returnFocus = false) {
  menuOpen.value = false

  if (returnFocus) {
    menuButton.value?.focus()
  }
}

/** Escape closes the menu and gives the focus back to the button that opened it. */
function onEscape() {
  if (menuOpen.value) {
    closeMenu(true)
  }
}

/** A tap anywhere but on the menu and its button closes the menu. */
function onDocumentClick(event: MouseEvent) {
  const target = event.target as Node | null

  if (target && !menuButton.value?.contains(target) && !menuPanel.value?.contains(target)) {
    closeMenu()
  }
}

// The listener is on the page only while the menu is open.
watch(menuOpen, (open) => {
  if (open) {
    document.addEventListener('click', onDocumentClick)
  } else {
    document.removeEventListener('click', onDocumentClick)
  }
})

onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick))

function startReport() {
  closeMenu()
  reporting.value = true
}

/** Back from the report dialog: the focus returns to the menu button, as the menu that had it is closed. */
async function closeReport() {
  reporting.value = false
  await nextTick()
  menuButton.value?.focus()
}

function startRemove() {
  closeMenu()
  confirmingRemove.value = true
}

async function remove() {
  busy.value = true
  error.value = ''

  try {
    // The post leaves the cache, and with it every list and page that shows it.
    await posts.remove(props.post.id)
  } catch (e) {
    error.value = errorMessage(e)
    confirmingRemove.value = false
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <article class="flex flex-col gap-3 rounded-2xl border border-line bg-surface p-4" @keydown.esc="onEscape">
    <header class="flex items-center gap-3">
      <ReaderAvatar :name="post.author.name" />
      <div class="min-w-0 flex-1">
        <RouterLink
          :to="{ name: 'reader', params: { handle: post.author.handle } }"
          class="block truncate font-semibold underline-offset-2 hover:underline"
        >
          {{ post.author.name }}
        </RouterLink>
        <p class="truncate text-sm text-ink-muted">
          @{{ post.author.handle }}<template v-if="date"> · {{ date }}</template>
        </p>
      </div>

      <div class="-mr-2 shrink-0">
        <button
          ref="menuButton"
          type="button"
          :aria-label="$t('social.post.menu')"
          :aria-expanded="menuOpen"
          :aria-controls="menuId"
          class="grid size-11 place-items-center rounded-full text-ink-muted hover:bg-sunken"
          @click="menuOpen = !menuOpen"
        >
          <svg viewBox="0 0 24 24" class="size-6" fill="currentColor" aria-hidden="true">
            <circle cx="5" cy="12" r="1.8" />
            <circle cx="12" cy="12" r="1.8" />
            <circle cx="19" cy="12" r="1.8" />
          </svg>
        </button>
      </div>
    </header>

    <div v-if="menuOpen" :id="menuId" ref="menuPanel" class="flex flex-wrap gap-2 rounded-xl bg-sunken p-2">
      <template v-if="post.is_mine">
        <RouterLink
          :to="{ name: 'community-post', params: { id: post.id }, query: { edit: '1' } }"
          class="inline-flex min-h-11 items-center rounded-lg bg-surface px-3 text-sm font-medium"
        >
          {{ $t('social.post.edit') }}
        </RouterLink>
        <button
          type="button"
          class="min-h-11 rounded-lg bg-surface px-3 text-sm font-medium text-danger"
          @click="startRemove"
        >
          {{ $t('social.post.remove') }}
        </button>
      </template>
      <button v-else type="button" class="min-h-11 rounded-lg bg-surface px-3 text-sm font-medium" @click="startReport">
        {{ $t('social.post.report') }}
      </button>
    </div>

    <div v-if="post.is_hidden" role="status" class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">
      <p class="font-semibold">{{ $t('social.post.hidden') }}</p>
      <p>{{ $t('social.post.hiddenHint') }}</p>
    </div>

    <p v-if="showBook" class="text-sm text-ink-muted">
      <RouterLink
        v-if="libraryEntry"
        :to="{ name: 'book', params: { id: libraryEntry.id } }"
        class="font-medium text-ink underline-offset-2 hover:underline"
      >
        {{ post.book.title }}
      </RouterLink>
      <span v-else class="font-medium text-ink">{{ post.book.title }}</span>
      <template v-if="authors"> · {{ authors }}</template>
      <template v-if="post.type === 'quote' && post.page"> · {{ $t('social.post.quotePage', { page: post.page }) }}</template>
    </p>

    <template v-if="post.type === 'quote'">
      <blockquote
        class="whitespace-pre-line break-words border-l-4 border-accent pl-3 font-display text-lg leading-relaxed"
        :class="detail ? '' : 'line-clamp-6'"
      >
        {{ post.content }}
      </blockquote>
      <p v-if="post.comment" class="whitespace-pre-line break-words text-sm text-ink-muted" :class="detail ? '' : 'line-clamp-4'">
        {{ post.comment }}
      </p>
    </template>

    <template v-else>
      <div class="flex items-center gap-2">
        <span class="rounded-full bg-sunken px-2.5 py-0.5 text-xs font-medium">{{ $t('social.post.review') }}</span>
        <RatingStars :model-value="post.rating" readonly :label="ratingLabel" />
      </div>
      <p v-if="post.content" class="whitespace-pre-line break-words" :class="detail ? '' : 'line-clamp-6'">{{ post.content }}</p>
    </template>

    <ErrorNotice v-if="error" :message="error" />

    <div v-if="confirmingRemove" class="flex flex-col gap-2 border-t border-line pt-3">
      <p class="text-sm">{{ $t('social.post.removeConfirm') }}</p>
      <div class="flex gap-2">
        <button
          type="button"
          :disabled="busy"
          class="min-h-11 flex-1 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
          @click="remove"
        >
          {{ $t('social.post.removeYes') }}
        </button>
        <button
          type="button"
          class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-4 font-medium"
          @click="confirmingRemove = false"
        >
          {{ $t('common.cancel') }}
        </button>
      </div>
    </div>

    <footer class="-mx-2 -mb-2 flex items-center gap-1 border-t border-line pt-1">
      <button
        type="button"
        :aria-pressed="post.liked_by_me"
        data-testid="like"
        class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 text-sm font-medium hover:bg-sunken"
        :class="post.liked_by_me ? 'text-accent' : 'text-ink-muted'"
        @click="toggleLike"
      >
        <svg
          viewBox="0 0 24 24"
          class="size-5"
          :fill="post.liked_by_me ? 'currentColor' : 'none'"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <path d="M12 20.5s-8-4.9-8-10.6A4.4 4.4 0 0 1 12 7.3a4.4 4.4 0 0 1 8 2.6c0 5.7-8 10.6-8 10.6z" />
        </svg>
        <!-- The name of the button is "Подобається 3": the words and the number together. -->
        <span class="sr-only">{{ $t('social.post.like') }}</span>
        <span class="tabular-nums" data-testid="likes">{{ post.likes_count }}</span>
      </button>

      <component
        :is="detail ? 'span' : 'RouterLink'"
        :to="detail ? undefined : { name: 'community-post', params: { id: post.id } }"
        class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-ink-muted"
        :class="detail ? '' : 'hover:bg-sunken'"
      >
        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">
          <path d="M4 5.5h16v10H9.5L5 19.5v-4H4z" />
        </svg>
        <span class="sr-only">{{ $t('social.post.comments', { n: post.comments_count }, post.comments_count) }}</span>
        <span class="tabular-nums" aria-hidden="true" data-testid="comments">{{ post.comments_count }}</span>
      </component>
    </footer>

    <ReportDialog v-if="reporting" :target="{ kind: 'post', id: post.id }" @close="closeReport" />
  </article>
</template>
