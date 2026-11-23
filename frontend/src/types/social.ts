/** Types of the community: posts, comments, readers, reports. */

/** A reader as other readers see them in a post or a comment. Never an e-mail address or a time zone. */
export interface Author {
  handle: string
  name: string
}

export type PostType = 'quote' | 'review'

/** The catalog book a post is about, not the private library entry of its author. */
export interface PostBook {
  id: number
  title: string
  authors: string[]
  cover_url: string | null
}

/** A published quote (a snapshot of its text) or review, as the current reader sees it. */
export interface Post {
  id: number
  type: PostType
  /** The quoted text, or the text of the review (empty for a rating alone). */
  content: string | null
  /** The author's own thought about a quoted passage. */
  comment: string | null
  /** 1-5, reviews only. */
  rating: number | null
  page: number | null
  likes_count: number
  /** What the current reader sees: hidden comments and those of blocked readers are not counted. */
  comments_count: number
  liked_by_me: boolean
  is_mine: boolean
  /** Hidden by moderation. Only the author is ever shown such a post. */
  is_hidden: boolean
  created_at: string
  updated_at: string
  author: Author
  book: PostBook
}

export interface PostsPage {
  data: Post[]
  links: { next: string | null }
}

export interface BookReviewsPage extends PostsPage {
  meta: {
    /** The review of the current reader, wherever it is in the list (it may be on a page not loaded yet). */
    my_review_id: number | null
    reviews_count: number
    /** One decimal, null while there is no review. */
    average_rating: number | null
  }
}

export interface Comment {
  id: number
  post_id: number
  content: string
  created_at: string
  author: Author
  is_mine: boolean
  /** The author of the comment and the author of the post may delete it. */
  can_delete: boolean
  /** Hidden by moderation. Only the author is ever shown such a comment. */
  is_hidden: boolean
}

export interface CommentsPage {
  data: Comment[]
  links: { next: string | null }
}

/** A reader in a list: search results, blocked readers. */
export interface Reader {
  handle: string
  name: string
  bio: string | null
  is_me: boolean
  is_following: boolean
}

export interface ReadersPage {
  data: Reader[]
  links: { next: string | null }
}

/** The public profile of a reader, with numbers as the current reader may see them. */
export interface Profile {
  handle: string
  name: string
  bio: string | null
  posts_count: number
  followers_count: number
  following_count: number
  is_following: boolean
  is_me: boolean
  /** The current reader has blocked this reader. */
  is_blocked: boolean
}

export type FeedScope = 'following' | 'all'

export type NewPostPayload =
  | { type: 'quote'; quote_id: number; comment?: string | null }
  | { type: 'review'; user_book_id: number; rating: number; content?: string | null }

/** A quote post has its comment to change, a review its rating and text. */
export interface UpdatePostPayload {
  comment?: string | null
  rating?: number
  content?: string | null
}

export type ReportReason = 'spam' | 'abuse' | 'other'

export interface ReportPayload {
  reason: ReportReason
  note?: string | null
}

/** What can be reported. */
export type ReportTarget = { kind: 'post' | 'comment'; id: number }
