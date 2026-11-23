import type { FeedPage, PeoplePage, PersonSearchResult, PublicProfile, QuotesPage, ReviewsPage } from '@/types/api'
import { http } from './http'

const user = (username: string) => `/api/users/${encodeURIComponent(username)}`

export const socialApi = {
  profile: (username: string) => http.get<{ data: PublicProfile }>(user(username)).then((response) => response.data),
  quotes: (username: string, page = 1) => http.get<QuotesPage>(`${user(username)}/quotes?page=${page}`),
  reviews: (username: string, page = 1) => http.get<ReviewsPage>(`${user(username)}/reviews?page=${page}`),
  follow: (username: string) => http.post<null>(`${user(username)}/follow`),
  unfollow: (username: string) => http.delete<null>(`${user(username)}/follow`),
  followers: (username: string, page = 1) => http.get<PeoplePage>(`${user(username)}/followers?page=${page}`),
  following: (username: string, page = 1) => http.get<PeoplePage>(`${user(username)}/following?page=${page}`),
  search: (q: string) =>
    http.get<{ data: PersonSearchResult[] }>(`/api/users?q=${encodeURIComponent(q)}`).then((response) => response.data),
  block: (username: string) => http.post<null>(`${user(username)}/block`),
  unblock: (username: string) => http.delete<null>(`${user(username)}/block`),
  blocked: (page = 1) => http.get<PeoplePage>(`/api/me/blocks?page=${page}`),
  like: (quoteId: number) => http.post<null>(`/api/quotes/${quoteId}/like`),
  unlike: (quoteId: number) => http.delete<null>(`/api/quotes/${quoteId}/like`),
  feed: (page = 1) => http.get<FeedPage>(`/api/feed?page=${page}`),
}
