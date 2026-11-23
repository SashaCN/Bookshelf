import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { useAuthStore } from '@/stores/auth'
import { makeFeedQuote } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { QuoteComment } from '@/types/api'
import QuoteCard from './QuoteCard.vue'

vi.mock('@/api/social', () => ({
  socialApi: { like: vi.fn(), unlike: vi.fn(), comments: vi.fn(), addComment: vi.fn(), deleteComment: vi.fn() },
}))

const viewer = { id: 1, name: 'Іван', email: 'ivan@example.com', timezone: 'Europe/Kyiv', username: 'ivan', is_public: true, new_followers_count: 0 }

function makeComment(overrides: Partial<QuoteComment> = {}): QuoteComment {
  return {
    id: 1,
    body: 'Влучно сказано',
    created_at: '2026-10-05T10:00:00+00:00',
    author: { username: 'taras', name: 'Тарас' },
    can_delete: false,
    ...overrides,
  }
}

function mountCard(signedIn = true, count = 2) {
  const { plugins, router } = createTestEnvironment()
  useAuthStore().user = signedIn ? viewer : null
  const wrapper = mount(QuoteCard, { props: { quote: makeFeedQuote({ id: 7, comments_count: count }), readonly: true }, global: { plugins } })
  return { wrapper, router }
}

async function open(wrapper: ReturnType<typeof mount>) {
  await wrapper.get('button[aria-expanded]').trigger('click')
  await flushPromises()
}

describe('quote comments', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('shows the count on a collapsed button and loads nothing until it is opened', async () => {
    const { wrapper } = mountCard()
    const toggle = wrapper.get('button[aria-expanded]')

    expect(toggle.text()).toBe('2')
    expect(toggle.attributes('aria-label')).toBe('Коментарі, 2 коментарі')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    expect(socialApi.comments).not.toHaveBeenCalled()
  })

  it('has no comments button on a card that carries no count', () => {
    const { plugins } = createTestEnvironment()
    const wrapper = mount(QuoteCard, { props: { quote: makeFeedQuote(), readonly: true }, global: { plugins } })

    expect(wrapper.find('button[aria-expanded]').exists()).toBe(false)
  })

  it('opens the thread, loads it lazily and renders names, links and plain text with line breaks', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue({
      data: [
        makeComment({ body: 'Перший\n<b>рядок</b>' }),
        makeComment({ id: 2, author: { username: null, name: null }, body: 'Анонімно' }),
      ],
      links: { next: null },
    })
    const { wrapper } = mountCard()

    await open(wrapper)

    expect(socialApi.comments).toHaveBeenCalledWith(7, 1)
    expect(wrapper.get('button[aria-expanded]').attributes('aria-expanded')).toBe('true')
    const comments = wrapper.findAll('[data-test="comment"]')
    expect(comments).toHaveLength(2)
    expect(comments[0].find('a').attributes('href')).toBe('/u/taras')
    expect(comments[0].get('[data-test="comment-body"]').text()).toBe('Перший\n<b>рядок</b>')
    expect(comments[0].find('b').exists()).toBe(false)
    expect(comments[0].get('[data-test="comment-body"]').classes()).toContain('whitespace-pre-line')
    expect(comments[1].text()).toContain('Читач')
    expect(comments[1].find('a').exists()).toBe(false)
  })

  it('shows an empty thread and an error with retry', async () => {
    vi.mocked(socialApi.comments).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValue({ data: [], links: { next: null } })
    const { wrapper } = mountCard()

    await open(wrapper)
    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-test="comments-empty"]').exists()).toBe(true)
  })

  it('loads the next page without duplicating comments', async () => {
    vi.mocked(socialApi.comments)
      .mockResolvedValueOnce({ data: [makeComment()], links: { next: '/x?page=2' } })
      .mockResolvedValueOnce({ data: [makeComment(), makeComment({ id: 2 })], links: { next: null } })
    const { wrapper } = mountCard()
    await open(wrapper)

    const more = wrapper.findAll('button').find((button) => button.text() === 'Показати ще')!
    await more.trigger('click')
    await flushPromises()

    expect(socialApi.comments).toHaveBeenLastCalledWith(7, 2)
    expect(wrapper.findAll('[data-test="comment"]')).toHaveLength(2)
    expect(wrapper.findAll('button').some((button) => button.text() === 'Показати ще')).toBe(false)
  })

  it('posts a trimmed comment, appends it, clears the field and bumps the count', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue({ data: [], links: { next: null } })
    vi.mocked(socialApi.addComment).mockResolvedValue(makeComment({ id: 5, body: 'Нове', can_delete: true }))
    const { wrapper } = mountCard()
    await open(wrapper)

    const textarea = wrapper.get('textarea')
    const label = wrapper.get(`label[for="${textarea.attributes('id')}"]`)
    expect(label.text()).toBe('Ваш коментар')
    const submit = wrapper.get('button[type="submit"]')
    expect(submit.attributes('disabled')).toBeDefined()

    await textarea.setValue('  Нове  ')
    expect(wrapper.find('[data-test="comment-counter"]').exists()).toBe(false)
    expect(submit.attributes('disabled')).toBeUndefined()
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(socialApi.addComment).toHaveBeenCalledWith(7, 'Нове')
    expect(wrapper.findAll('[data-test="comment"]')).toHaveLength(1)
    expect((textarea.element as HTMLTextAreaElement).value).toBe('')
    expect(wrapper.get('button[aria-expanded]').text()).toBe('3')
  })

  it('shows the counter only close to the limit', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue({ data: [], links: { next: null } })
    const { wrapper } = mountCard()
    await open(wrapper)

    await wrapper.get('textarea').setValue('а'.repeat(800))

    expect(wrapper.get('[data-test="comment-counter"]').text()).toBe('800 з 1000')
    expect(wrapper.get('textarea').attributes('rows')).toBe('6')
  })

  it('limits the field to 1000 characters', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue({ data: [], links: { next: null } })
    const { wrapper } = mountCard()
    await open(wrapper)

    expect(wrapper.get('textarea').attributes('maxlength')).toBe('1000')
  })

  it('keeps the text and shows the error when posting fails', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue({ data: [], links: { next: null } })
    vi.mocked(socialApi.addComment).mockRejectedValue(new ApiError(429, null))
    const { wrapper } = mountCard()
    await open(wrapper)

    await wrapper.get('textarea').setValue('Привіт')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Забагато запитів')
    expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('Привіт')
    expect(wrapper.get('button[aria-expanded]').text()).toBe('2')
  })

  it('disables the button while sending', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue({ data: [], links: { next: null } })
    vi.mocked(socialApi.addComment).mockReturnValue(new Promise(() => {}))
    const { wrapper } = mountCard()
    await open(wrapper)

    await wrapper.get('textarea').setValue('Привіт')
    await wrapper.get('form').trigger('submit')

    const submit = wrapper.get('button[type="submit"]')
    expect(submit.attributes('disabled')).toBeDefined()
    expect(submit.text()).toBe('Надсилаємо…')
  })

  it('offers delete only where allowed, after a confirmation, and lowers the count', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue({
      data: [makeComment({ id: 1, can_delete: true }), makeComment({ id: 2, can_delete: false })],
      links: { next: null },
    })
    vi.mocked(socialApi.deleteComment).mockResolvedValue(null)
    const { wrapper } = mountCard()
    await open(wrapper)

    const comments = wrapper.findAll('[data-test="comment"]')
    expect(comments[1].findAll('button')).toHaveLength(0)

    await comments[0].get('button').trigger('click')
    expect(socialApi.deleteComment).not.toHaveBeenCalled()
    expect(comments[0].text()).toContain('Видалити цей коментар?')

    await comments[0].findAll('button').find((button) => button.text() === 'Так, видалити')!.trigger('click')
    await flushPromises()

    expect(socialApi.deleteComment).toHaveBeenCalledWith(7, 1)
    expect(wrapper.findAll('[data-test="comment"]')).toHaveLength(1)
    expect(wrapper.get('button[aria-expanded]').text()).toBe('1')
  })

  it('shows a failed delete and keeps the comment', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue({ data: [makeComment({ can_delete: true })], links: { next: null } })
    vi.mocked(socialApi.deleteComment).mockRejectedValue(new ApiError(404, null))
    const { wrapper } = mountCard()
    await open(wrapper)

    await wrapper.get('[data-test="comment"] button').trigger('click')
    await wrapper.findAll('button').find((button) => button.text() === 'Так, видалити')!.trigger('click')
    await flushPromises()

    expect(wrapper.findAll('[data-test="comment"]')).toHaveLength(1)
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  })

  it('lets a guest read, with a login link back to this page instead of the form', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue({ data: [makeComment()], links: { next: null } })
    const { wrapper, router } = mountCard(false)
    await router.push('/u/olena')
    await open(wrapper)

    expect(wrapper.findAll('[data-test="comment"]')).toHaveLength(1)
    expect(wrapper.find('textarea').exists()).toBe(false)
    const login = wrapper.findAll('a').find((link) => link.text() === 'Увійдіть, щоб коментувати')!
    expect(login.attributes('href')).toBe('/login?redirect=/u/olena')
  })

  it('closes the thread again', async () => {
    vi.mocked(socialApi.comments).mockResolvedValue({ data: [makeComment()], links: { next: null } })
    const { wrapper } = mountCard()
    await open(wrapper)

    await wrapper.get('button[aria-expanded]').trigger('click')

    expect(wrapper.find('[data-test="comment"]').exists()).toBe(false)
    expect(wrapper.get('button[aria-expanded]').attributes('aria-expanded')).toBe('false')
  })
})
