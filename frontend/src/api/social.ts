import type {
  BookReviewsPage,
  Comment,
  CommentsPage,
  FeedScope,
  NewPostPayload,
  Post,
  PostsPage,
  Profile,
  ReadersPage,
  ReportPayload,
  ReportTarget,
  UpdatePostPayload,
} from '@/types/social'
import { http } from './http'

function pageQuery(page: number, params: Record<string, string> = {}): string {
  return new URLSearchParams({ ...params, page: String(page) }).toString()
}

const profilePath = (handle: string) => `/api/profiles/${encodeURIComponent(handle)}`

export const socialApi = {
  feed: (scope: FeedScope, page = 1) => http.get<PostsPage>(`/api/feed?${pageQuery(page, { scope })}`),

  searchReaders: (query: string, page = 1) => http.get<ReadersPage>(`/api/profiles?${pageQuery(page, { q: query })}`),
  profile: (handle: string) => http.get<{ data: Profile }>(profilePath(handle)).then((response) => response.data),
  profilePosts: (handle: string, page = 1) => http.get<PostsPage>(`${profilePath(handle)}/posts?${pageQuery(page)}`),
  follow: (handle: string) =>
    http.put<{ data: Profile }>(`${profilePath(handle)}/follow`).then((response) => response.data),
  unfollow: (handle: string) =>
    http.delete<{ data: Profile }>(`${profilePath(handle)}/follow`).then((response) => response.data),

  blocks: (page = 1) => http.get<ReadersPage>(`/api/blocks?${pageQuery(page)}`),
  block: (handle: string) =>
    http.put<{ data: Profile }>(`/api/blocks/${encodeURIComponent(handle)}`).then((response) => response.data),
  unblock: (handle: string) => http.delete<null>(`/api/blocks/${encodeURIComponent(handle)}`),

  getPost: (id: number) => http.get<{ data: Post }>(`/api/posts/${id}`).then((response) => response.data),
  createPost: (payload: NewPostPayload) =>
    http.post<{ data: Post }>('/api/posts', payload).then((response) => response.data),
  updatePost: (id: number, payload: UpdatePostPayload) =>
    http.patch<{ data: Post }>(`/api/posts/${id}`, payload).then((response) => response.data),
  deletePost: (id: number) => http.delete<null>(`/api/posts/${id}`),
  /** Both answer with the post, so the counter on screen is the exact one. */
  like: (id: number) => http.put<{ data: Post }>(`/api/posts/${id}/like`).then((response) => response.data),
  /** Null when the reader may no longer see the post (its author blocked them): the like is gone all the same. */
  unlike: (id: number) => http.delete<{ data: Post | null }>(`/api/posts/${id}/like`).then((response) => response.data),

  comments: (postId: number, page = 1) => http.get<CommentsPage>(`/api/posts/${postId}/comments?${pageQuery(page)}`),
  addComment: (postId: number, content: string) =>
    http.post<{ data: Comment }>(`/api/posts/${postId}/comments`, { content }).then((response) => response.data),
  deleteComment: (id: number) => http.delete<null>(`/api/comments/${id}`),

  report: (target: ReportTarget, payload: ReportPayload) =>
    http.post<unknown>(`/api/${target.kind === 'post' ? 'posts' : 'comments'}/${target.id}/report`, payload),

  bookReviews: (bookId: number, page = 1) => http.get<BookReviewsPage>(`/api/books/${bookId}/reviews?${pageQuery(page)}`),
}
