import type { FeedPage, PeoplePage, PersonSearchResult, PublicProfile, QuotesPage } from '@/types/api'
import { http } from './http'

const user = (username: string) => `/api/users/${encodeURIComponent(username)}`

export const socialApi = {
  profile: (username: string) => http.get<{ data: PublicProfile }>(user(username)).then((response) => response.data),
  quotes: (username: string, page = 1) => http.get<QuotesPage>(`${user(username)}/quotes?page=${page}`),
  follow: (username: string) => http.post<null>(`${user(username)}/follow`),
  unfollow: (username: string) => http.delete<null>(`${user(username)}/follow`),
  followers: (username: string, page = 1) => http.get<PeoplePage>(`${user(username)}/followers?page=${page}`),
  following: (username: string, page = 1) => http.get<PeoplePage>(`${user(username)}/following?page=${page}`),
  search: (q: string) =>
    http.get<{ data: PersonSearchResult[] }>(`/api/users?q=${encodeURIComponent(q)}`).then((response) => response.data),
  feed: (page = 1) => http.get<FeedPage>(`/api/feed?page=${page}`),
}
