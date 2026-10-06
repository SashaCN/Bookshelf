import { defineStore } from 'pinia'
import { shallowReactive } from 'vue'
import { socialApi } from '@/api/social'
import type { NewPostPayload, Post, UpdatePostPayload } from '@/types/social'
import { useProfilesStore } from './profiles'

/**
 * Every post the reader has been shown, by id. The feed, the profiles, the post page and the reviews of a book keep
 * only lists of ids, so a like or a comment made in one place shows in all the others.
 */
export const usePostsStore = defineStore('posts', () => {
  const byId = shallowReactive(new Map<number, Post>())
  /** Posts whose like is on its way: a second tap meanwhile would race the first for the final state. */
  const liking = new Set<number>()
  /**
   * Bumped by reset, so an answer that arrives after somebody else has signed in (or the reader has signed out) is
   * dropped instead of putting the previous reader's post into the cache.
   */
  let generation = 0

  function get(id: number): Post | undefined {
    return byId.get(id)
  }

  function put(post: Post): void {
    byId.set(post.id, post)
  }

  function putAll(posts: Post[]): void {
    for (const post of posts) {
      put(post)
    }
  }

  /** A fresh copy from the server; throws, for example a 404 for a post that is gone or not for this reader. */
  async function fetch(id: number): Promise<Post> {
    const current = generation
    const post = await socialApi.getPost(id)

    if (current === generation) {
      put(post)
    }

    return post
  }

  async function create(payload: NewPostPayload): Promise<Post> {
    const current = generation
    const post = await socialApi.createPost(payload)

    if (current === generation) {
      put(post)
      // The number on the reader's own profile, and the list of their posts there, follow.
      useProfilesStore().addOwnPost(post)
    }

    return post
  }

  async function update(id: number, payload: UpdatePostPayload): Promise<Post> {
    const current = generation
    const post = await socialApi.updatePost(id, payload)

    if (current === generation) {
      put(post)
    }

    return post
  }

  async function remove(id: number): Promise<void> {
    const current = generation
    const removed = byId.get(id)

    await socialApi.deletePost(id)

    if (current === generation) {
      byId.delete(id)

      if (removed?.is_mine) {
        useProfilesStore().changeOwnPostsCount(-1)
      }
    }
  }

  /**
   * The heart changes at once and the request follows. The server answers with the post, so the counter ends up exact;
   * if it turns the request down the heart goes back and the error is thrown. An answer without a post (the reader
   * took a like back from a post they can no longer see) leaves the heart as it is.
   */
  async function toggleLike(id: number): Promise<void> {
    const before = byId.get(id)

    if (!before || liking.has(id)) {
      return
    }

    const current = generation
    liking.add(id)
    const wantsLike = !before.liked_by_me
    put({ ...before, liked_by_me: wantsLike, likes_count: Math.max(0, before.likes_count + (wantsLike ? 1 : -1)) })

    try {
      const fresh = await (wantsLike ? socialApi.like(id) : socialApi.unlike(id))

      if (current === generation && fresh) {
        put(fresh)
      }
    } catch (error) {
      const latest = byId.get(id)
      if (current === generation && latest) {
        put({ ...latest, liked_by_me: before.liked_by_me, likes_count: before.likes_count })
      }
      throw error
    } finally {
      if (current === generation) {
        liking.delete(id)
      }
    }
  }

  /** A comment was added (+1) or deleted (-1) on the post page. */
  function changeCommentsCount(id: number, by: number): void {
    const post = byId.get(id)
    if (post) {
      put({ ...post, comments_count: Math.max(0, post.comments_count + by) })
    }
  }

  /** The reader has blocked an author: nothing of theirs is to be shown any more. */
  function removeByAuthor(handle: string): void {
    for (const post of [...byId.values()]) {
      if (post.author.handle === handle) {
        byId.delete(post.id)
      }
    }
  }

  /** Forget everything, e.g. when somebody else signs in on the same device. */
  function reset(): void {
    generation++
    byId.clear()
    liking.clear()
  }

  return { get, put, putAll, fetch, create, update, remove, toggleLike, changeCommentsCount, removeByAuthor, reset }
})
