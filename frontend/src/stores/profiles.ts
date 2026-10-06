import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { socialApi } from '@/api/social'
import type { Post, Profile } from '@/types/social'
import { useBlocksStore } from './blocks'
import { useFeedStore } from './feed'
import { usePostsStore } from './posts'

/** The public profile the reader is looking at, with their posts. */
export const useProfilesStore = defineStore('profiles', () => {
  const posts = usePostsStore()

  const profile = ref<Profile | null>(null)
  const postIds = ref<number[]>([])
  const hasMorePosts = ref(false)
  const postsLoaded = ref(false)
  const loadingMorePosts = ref(false)

  let page = 1
  /** Bumped by every load of another profile and by reset, so an answer for a profile already left is dropped. */
  let generation = 0
  /** Follow and block requests of the profile on screen go one at a time. */
  let busy = false

  const items = computed(() => postIds.value.map((id) => posts.get(id)).filter((post): post is Post => post !== undefined))

  /** Load a profile; a handle nobody has (or somebody who blocked the reader) answers 404, which is thrown. */
  async function load(handle: string): Promise<void> {
    if (profile.value?.handle !== handle) {
      profile.value = null
      postIds.value = []
      hasMorePosts.value = false
      postsLoaded.value = false
    }

    loadingMorePosts.value = false
    const current = ++generation

    try {
      const loadedProfile = await socialApi.profile(handle)
      if (current !== generation) return
      profile.value = loadedProfile
    } catch (error) {
      if (current === generation) throw error
    }
  }

  async function loadPosts(handle: string): Promise<void> {
    const current = generation

    try {
      const result = await socialApi.profilePosts(handle, 1)
      if (current !== generation) return
      posts.putAll(result.data)
      postIds.value = result.data.map((post) => post.id)
      hasMorePosts.value = result.links.next !== null
      page = 1
      postsLoaded.value = true
    } catch (error) {
      if (current === generation) throw error
    }
  }

  async function loadMorePosts(): Promise<void> {
    const handle = profile.value?.handle

    if (!handle || !hasMorePosts.value || loadingMorePosts.value) {
      return
    }

    const current = generation
    loadingMorePosts.value = true

    try {
      const result = await socialApi.profilePosts(handle, page + 1)
      if (current !== generation) return
      posts.putAll(result.data)
      const known = new Set(postIds.value)
      postIds.value = [...postIds.value, ...result.data.map((post) => post.id).filter((id) => !known.has(id))]
      hasMorePosts.value = result.links.next !== null
      page++
    } finally {
      if (current === generation) {
        loadingMorePosts.value = false
      }
    }
  }

  /** The reader published (+1) or deleted (-1) a post: the number on their own profile follows. */
  function changeOwnPostsCount(by: number): void {
    const current = profile.value

    if (current?.is_me) {
      profile.value = { ...current, posts_count: Math.max(0, current.posts_count + by) }
    }
  }

  /** The reader published a post: it goes to the top of the list on their own profile, if that is loaded. */
  function addOwnPost(post: Post): void {
    if (profile.value?.is_me) {
      if (postsLoaded.value) {
        postIds.value = [post.id, ...postIds.value.filter((id) => id !== post.id)]
      }
      changeOwnPostsCount(1)
    }
  }

  /** Swap in the server's copy of the profile, if it is still the one on screen. */
  function settle(fresh: Profile): void {
    if (profile.value?.handle === fresh.handle) {
      profile.value = fresh
    }
  }

  /**
   * Follow or unfollow: the button and the counter change at once and the request follows. If the server turns it down
   * everything goes back and the error is thrown.
   */
  async function toggleFollow(): Promise<void> {
    const before = profile.value

    if (!before || busy) {
      return
    }

    busy = true
    const wantsFollow = !before.is_following
    profile.value = {
      ...before,
      is_following: wantsFollow,
      followers_count: Math.max(0, before.followers_count + (wantsFollow ? 1 : -1)),
    }

    try {
      settle(await (wantsFollow ? socialApi.follow(before.handle) : socialApi.unfollow(before.handle)))
    } catch (error) {
      if (profile.value?.handle === before.handle) {
        profile.value = before
      }
      throw error
    } finally {
      busy = false
    }
  }

  /**
   * Block the reader. It is not done in advance (the reader loses the feed of this one), but the answer is used at once:
   * the posts of the blocked reader disappear from everywhere, and the follow between them is gone.
   */
  async function block(): Promise<void> {
    const handle = profile.value?.handle

    if (!handle || busy) {
      return
    }

    busy = true

    try {
      settle(await socialApi.block(handle))
      postIds.value = []
      hasMorePosts.value = false
      posts.removeByAuthor(handle)
      // Lists that were loaded before are out of date now.
      useFeedStore().reset()
      useBlocksStore().reset()
    } finally {
      busy = false
    }
  }

  async function unblock(): Promise<void> {
    const before = profile.value

    if (!before || busy) {
      return
    }

    busy = true

    try {
      await socialApi.unblock(before.handle)
      useBlocksStore().reset()
      useFeedStore().reset()

      try {
        await load(before.handle)
      } catch {
        // The reader may have blocked back, which hides the profile again: the unblocking itself went through.
        if (profile.value?.handle === before.handle) {
          profile.value = { ...before, is_blocked: false }
        }
      }
    } finally {
      busy = false
    }
  }

  /** Forget everything, e.g. when somebody else signs in on the same device. */
  function reset(): void {
    generation++
    profile.value = null
    postIds.value = []
    hasMorePosts.value = false
    postsLoaded.value = false
    loadingMorePosts.value = false
    page = 1
    busy = false
  }

  return {
    profile,
    items,
    hasMorePosts,
    postsLoaded,
    loadingMorePosts,
    load,
    loadPosts,
    loadMorePosts,
    changeOwnPostsCount,
    addOwnPost,
    toggleFollow,
    block,
    unblock,
    reset,
  }
})
