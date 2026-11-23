import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { usePostsStore } from '@/stores/posts'
import { makeReview } from '@/test/socialFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { BookReviewsPage, Post } from '@/types/social'
import BookReviews from './BookReviews.vue'

vi.mock('@/api/social', () => ({
  socialApi: { bookReviews: vi.fn(), like: vi.fn(), unlike: vi.fn(), deletePost: vi.fn(), report: vi.fn() },
}))

type Meta = BookReviewsPage['meta']

function page(reviews: Post[], next: string | null = null, meta: Partial<Meta> = {}): BookReviewsPage {
  return { data: reviews, links: { next }, meta: { my_review_id: null, reviews_count: reviews.length, average_rating: 4.3, ...meta } }
}

async function mountReviews(firstPage: BookReviewsPage, props: { bookId?: number; userBookId?: number } = {}) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(socialApi.bookReviews).mockResolvedValue(firstPage)
  await router.push('/library/7')

  const wrapper = mount(BookReviews, { props: { bookId: 3, userBookId: 7, ...props }, global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('BookReviews', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('asks for the reviews of the catalog book, not of the library entry', async () => {
    await mountReviews(page([]), { bookId: 3, userBookId: 7 })

    expect(socialApi.bookReviews).toHaveBeenCalledWith(3, 1)
  })

  it('shows the reviews, newest first as the server sends them, without repeating the book', async () => {
    const { wrapper } = await mountReviews(
      page([makeReview({ id: 2, content: 'Second review' }), makeReview({ id: 1, content: 'First review' })]),
    )

    const articles = wrapper.findAll('article')

    expect(articles).toHaveLength(2)
    expect(articles[0]!.text()).toContain('Second review')
    expect(articles[1]!.text()).toContain('First review')
    expect(wrapper.text()).not.toContain('Atomic Habits')
  })

  it('shows the average rating with the number of reviews', async () => {
    const { wrapper } = await mountReviews(page([makeReview()], null, { reviews_count: 12, average_rating: 4.3 }))

    expect(wrapper.find('[aria-label="Середня оцінка: 4,3 з 5"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('4,3')
    expect(wrapper.text()).toContain('12 відгуків')
  })

  it.each([
    [1, '1 відгук'],
    [2, '2 відгуки'],
    [5, '5 відгуків'],
  ])('counts %i reviews in good Ukrainian', async (count, text) => {
    const { wrapper } = await mountReviews(page([makeReview()], null, { reviews_count: count, average_rating: 5 }))

    expect(wrapper.text()).toContain(text)
  })

  it('says there are no reviews yet, and shows no rating', async () => {
    const { wrapper } = await mountReviews(page([], null, { reviews_count: 0, average_rating: null }))

    expect(wrapper.text()).toContain('Відгуків про цю книгу поки немає.')
    expect(wrapper.find('[aria-label^="Середня оцінка"]').exists()).toBe(false)
  })

  it('offers to write a review, from the library entry', async () => {
    const { wrapper } = await mountReviews(page([makeReview({ is_mine: false })]), { userBookId: 7 })

    const write = wrapper.findAll('a').find((link) => link.text() === 'Написати відгук')

    expect(write?.attributes('href')).toBe('/community/new?book=7')
  })

  it('points to the reader\'s own review instead, when there is one', async () => {
    const { wrapper } = await mountReviews(page([makeReview({ id: 4, is_mine: true })], null, { my_review_id: 4 }))

    const links = wrapper.findAll('a').map((link) => [link.text(), link.attributes('href')])

    expect(links).toContainEqual(['Мій відгук', '/community/posts/4'])
    expect(wrapper.findAll('a').some((link) => link.text() === 'Написати відгук')).toBe(false)
  })

  describe('the review of the reader that is not in the list', () => {
    it('is still found: the link is to the review, and there is no offer to write another', async () => {
      // 25 reviews, the reader\'s own the oldest, on the second page, which has not been asked for.
      const { wrapper } = await mountReviews(page([makeReview({ id: 30 })], '/api/books/3/reviews?page=2', { my_review_id: 9, reviews_count: 25 }))

      const links = wrapper.findAll('a').map((link) => [link.text(), link.attributes('href')])

      expect(links).toContainEqual(['Мій відгук', '/community/posts/9'])
      expect(wrapper.findAll('a').some((link) => link.text() === 'Написати відгук')).toBe(false)
    })

    it('does not stop the reader from writing one when they have none', async () => {
      const { wrapper } = await mountReviews(page([makeReview({ id: 30, is_mine: false })], '/next', { my_review_id: null }))

      expect(wrapper.findAll('a').some((link) => link.text() === 'Написати відгук')).toBe(true)
      expect(wrapper.findAll('a').some((link) => link.text() === 'Мій відгук')).toBe(false)
    })

    it('offers writing again once the review in the list has been deleted', async () => {
      const { wrapper } = await mountReviews(page([makeReview({ id: 4, is_mine: true })], null, { my_review_id: 4 }))
      vi.mocked(socialApi.deletePost).mockResolvedValue(null)

      await wrapper.get('[aria-label="Дії з публікацією"]').trigger('click')
      await buttonWithText(wrapper, 'Видалити').trigger('click')
      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('a').some((link) => link.text() === 'Мій відгук')).toBe(false)
      expect(wrapper.findAll('a').some((link) => link.text() === 'Написати відгук')).toBe(true)
    })
  })

  describe('paging', () => {
    it('shows "show more" only while there is a next page, and adds the next page below', async () => {
      const { wrapper } = await mountReviews(page([makeReview({ id: 2 })], '/api/books/3/reviews?page=2', { reviews_count: 2, average_rating: 4 }))
      vi.mocked(socialApi.bookReviews).mockResolvedValue(page([makeReview({ id: 1 })], null, { reviews_count: 2, average_rating: 4 }))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(socialApi.bookReviews).toHaveBeenLastCalledWith(3, 2)
      expect(wrapper.findAll('article')).toHaveLength(2)
      expect(wrapper.findAll('button').some((button) => button.text() === 'Показати ще')).toBe(false)
    })

    it('does not show a review twice when a new one has pushed it onto the next page', async () => {
      const { wrapper } = await mountReviews(page([makeReview({ id: 2 })], '/next'))
      vi.mocked(socialApi.bookReviews).mockResolvedValue(page([makeReview({ id: 2 }), makeReview({ id: 1 })]))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('article')).toHaveLength(2)
    })

    it('keeps the reviews and offers a retry when the next page fails', async () => {
      const { wrapper } = await mountReviews(page([makeReview({ id: 2 })], '/next'))
      vi.mocked(socialApi.bookReviews).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(page([makeReview({ id: 1 })]))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('article')).toHaveLength(1)
      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('article')).toHaveLength(2)
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    })
  })

  describe('when the reviews cannot be loaded', () => {
    it('says so without getting in the way, and tries again on request', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(socialApi.bookReviews).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(page([makeReview()]))
      await router.push('/library/7')
      const wrapper = mount(BookReviews, { props: { bookId: 3, userBookId: 7 }, global: { plugins } })
      await flushPromises()

      expect(wrapper.text()).toContain('Не вдалося завантажити відгуки.')
      expect(wrapper.findAll('a').some((link) => link.text() === 'Написати відгук')).toBe(true)

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('article')).toHaveLength(1)
      expect(wrapper.text()).not.toContain('Не вдалося завантажити відгуки.')
    })
  })

  it('shows a like made on a review, because the reviews are in the shared cache', async () => {
    const { wrapper } = await mountReviews(page([makeReview({ id: 2, likes_count: 1 })]))
    vi.mocked(socialApi.like).mockResolvedValue(makeReview({ id: 2, liked_by_me: true, likes_count: 2 }))

    await wrapper.get('[data-testid="like"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="likes"]').text()).toBe('2')
    expect(usePostsStore().get(2)?.liked_by_me).toBe(true)
  })

  it('loads again for another book', async () => {
    const { wrapper } = await mountReviews(page([makeReview({ id: 2 })]))
    vi.mocked(socialApi.bookReviews).mockResolvedValue(page([], null, { reviews_count: 0, average_rating: null }))

    await wrapper.setProps({ bookId: 4 })
    await flushPromises()

    expect(socialApi.bookReviews).toHaveBeenLastCalledWith(4, 1)
    expect(wrapper.findAll('article')).toHaveLength(0)
  })

  it('shows nothing of the previous book while the next one loads', async () => {
    const { wrapper } = await mountReviews(
      page([makeReview({ id: 2, is_mine: true })], '/next', { my_review_id: 2, reviews_count: 12, average_rating: 4.3 }),
    )
    vi.mocked(socialApi.bookReviews).mockReturnValue(new Promise(() => {}))

    await wrapper.setProps({ bookId: 4 })
    await flushPromises()

    expect(wrapper.findAll('article')).toHaveLength(0)
    expect(wrapper.find('[aria-label^="Середня оцінка"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('12 відгуків')
    expect(wrapper.text()).not.toContain('Мій відгук')
    expect(wrapper.text()).not.toContain('Відгуків про цю книгу поки немає.')
    expect(wrapper.findAll('button').some((button) => button.text() === 'Показати ще')).toBe(false)
  })

  it('asks for the first page of the next book, even when it was on another page of the previous', async () => {
    const { wrapper } = await mountReviews(page([makeReview({ id: 2 })], '/next'))
    vi.mocked(socialApi.bookReviews).mockResolvedValueOnce(page([makeReview({ id: 1 })]))
    await buttonWithText(wrapper, 'Показати ще').trigger('click')
    await flushPromises()

    vi.mocked(socialApi.bookReviews).mockResolvedValue(page([makeReview({ id: 8 })], '/next'))
    await wrapper.setProps({ bookId: 4 })
    await flushPromises()
    vi.mocked(socialApi.bookReviews).mockResolvedValue(page([makeReview({ id: 7 })]))
    await buttonWithText(wrapper, 'Показати ще').trigger('click')
    await flushPromises()

    expect(socialApi.bookReviews).toHaveBeenLastCalledWith(4, 2)
  })
})
