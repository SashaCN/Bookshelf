<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import CommentForm from '@/components/CommentForm.vue'
import CommentList from '@/components/CommentList.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import PostCard from '@/components/PostCard.vue'
import PostEditForm from '@/components/PostEditForm.vue'
import SkeletonList from '@/components/SkeletonList.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useCommentsStore } from '@/stores/comments'
import { useLibraryStore } from '@/stores/library'
import { usePostsStore } from '@/stores/posts'
import type { Post } from '@/types/social'

const props = defineProps<{ id: number }>()

const route = useRoute()
const router = useRouter()
const posts = usePostsStore()
const comments = useCommentsStore()
const library = useLibraryStore()
const errorMessage = useApiErrorMessage()

/** The post is read from the cache for what is shown, so a like made here shows in the feed too. */
const post = computed<Post | undefined>(() => posts.get(props.id))
const notFound = ref(false)
const error = ref('')
const commentsError = ref('')
const commentsMoreError = ref('')

const editing = computed(() => route.query.edit === '1' && post.value?.is_mine === true)

async function loadComments() {
  commentsError.value = ''
  commentsMoreError.value = ''

  try {
    await comments.load(props.id)
  } catch (e) {
    commentsError.value = errorMessage(e)
  }
}

async function load() {
  notFound.value = false
  error.value = ''

  try {
    await posts.fetch(props.id)
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      notFound.value = true
    } else {
      error.value = errorMessage(e)
    }
    return
  }

  void loadComments()
}

onMounted(() => {
  // Only feeds the link from the book of the post to the library: without it the title is simply not a link.
  library.load().catch(() => undefined)
  load()
})
watch(() => props.id, load)

async function loadMoreComments() {
  commentsMoreError.value = ''

  try {
    await comments.loadMore()
  } catch (e) {
    commentsMoreError.value = errorMessage(e)
  }
}

function stopEditing() {
  router.replace({ name: 'community-post', params: { id: props.id } })
}

// The reader deleted the post from this page (it left the cache): back to the feed.
watch(post, (current, previous) => {
  if (previous && !current) {
    router.replace({ name: 'community' })
  }
})
</script>

<template>
  <PageHeader :title="$t('social.post.title')" :back-to="{ name: 'community' }" />

  <p v-if="notFound" class="py-10 text-center text-ink-muted">{{ $t('social.post.notFound') }}</p>

  <ErrorNotice v-else-if="error && !post" :message="error" retryable @retry="load" />

  <SkeletonList v-else-if="!post" :count="2" tall />

  <div v-else class="flex flex-col gap-6">
    <PostCard :post="post" detail />

    <PostEditForm v-if="editing" :post="post" @done="stopEditing" />

    <section class="flex flex-col gap-3" aria-labelledby="post-comments-title">
      <h2 id="post-comments-title" class="font-display text-xl font-semibold">{{ $t('social.comments.title') }}</h2>

      <CommentForm :post-id="post.id" />

      <ErrorNotice v-if="commentsError" :message="commentsError" retryable @retry="loadComments" />

      <SkeletonList v-else-if="!comments.loaded || comments.postId !== id" :count="2" />

      <template v-else>
        <CommentList v-if="comments.items.length" :comments="comments.items" />
        <p v-else class="text-sm text-ink-muted">{{ $t('social.comments.empty') }}</p>

        <ErrorNotice v-if="commentsMoreError" :message="commentsMoreError" retryable @retry="loadMoreComments" />

        <button
          v-if="comments.hasMore && !commentsMoreError"
          type="button"
          :disabled="comments.loadingMore"
          class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
          @click="loadMoreComments"
        >
          {{ $t('social.comments.more') }}
        </button>
      </template>
    </section>
  </div>
</template>
