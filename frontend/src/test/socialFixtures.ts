import type { Comment, Post, Profile, Reader } from '@/types/social'

export function makePost(overrides: Partial<Post> = {}): Post {
  return {
    id: 1,
    type: 'quote',
    content: 'We are what we repeatedly do.',
    comment: null,
    rating: null,
    page: null,
    likes_count: 0,
    comments_count: 0,
    liked_by_me: false,
    is_mine: false,
    is_hidden: false,
    created_at: '2026-10-05T10:00:00+00:00',
    updated_at: '2026-10-05T10:00:00+00:00',
    author: { handle: 'olena_k', name: 'Olena Kovalenko' },
    book: { id: 1, title: 'Atomic Habits', authors: ['James Clear'], cover_url: null },
    ...overrides,
  }
}

export function makeReview(overrides: Partial<Post> = {}): Post {
  return makePost({ type: 'review', content: 'Worth reading twice.', rating: 4, ...overrides })
}

export function makeComment(overrides: Partial<Comment> = {}): Comment {
  return {
    id: 1,
    post_id: 1,
    content: 'Nice one!',
    created_at: '2026-10-05T11:00:00+00:00',
    author: { handle: 'taras_b', name: 'Taras Bondar' },
    is_mine: false,
    can_delete: false,
    is_hidden: false,
    ...overrides,
  }
}

export function makeProfile(overrides: Partial<Profile> = {}): Profile {
  return {
    handle: 'olena_k',
    name: 'Olena Kovalenko',
    bio: 'I read on trains.',
    posts_count: 3,
    followers_count: 5,
    following_count: 2,
    is_following: false,
    is_me: false,
    is_blocked: false,
    ...overrides,
  }
}

export function makeReader(overrides: Partial<Reader> = {}): Reader {
  return {
    handle: 'olena_k',
    name: 'Olena Kovalenko',
    bio: null,
    is_me: false,
    is_following: false,
    ...overrides,
  }
}
