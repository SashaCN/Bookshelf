import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { createTestEnvironment } from '@/test/utils'
import type { PersonSearchResult } from '@/types/api'
import PeopleSearchView from './PeopleSearchView.vue'

vi.mock('@/api/social', () => ({
  socialApi: { search: vi.fn(), follow: vi.fn(), unfollow: vi.fn() },
}))

const olena: PersonSearchResult = { username: 'olena', name: 'Олена', is_following: false }
const taras: PersonSearchResult = { username: 'taras', name: 'Тарас', is_following: true }

async function mountView(path = '/people') {
  const { plugins, router } = createTestEnvironment()
  await router.push(path)
  const wrapper = mount(PeopleSearchView, { global: { plugins } })
  await flushPromises()
  return { wrapper, router }
}

async function type(wrapper: Awaited<ReturnType<typeof mountView>>['wrapper'], text: string) {
  await wrapper.get('#people-search').setValue(text)
}

async function settle() {
  await vi.advanceTimersByTimeAsync(400)
  await flushPromises()
}

function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((r) => (resolve = r))
  return { promise, resolve }
}

describe('PeopleSearchView', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.resetAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows a hint and does not search under two characters', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('щонайменше 2 символи')
    await type(wrapper, 'о')
    await settle()

    expect(socialApi.search).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('щонайменше 2 символи')
  })

  it('debounces typing into one search', async () => {
    vi.mocked(socialApi.search).mockResolvedValue([])
    const { wrapper } = await mountView()

    await type(wrapper, 'ол')
    await vi.advanceTimersByTimeAsync(200)
    await type(wrapper, 'оле')
    await vi.advanceTimersByTimeAsync(200)
    expect(socialApi.search).not.toHaveBeenCalled()

    await settle()
    expect(socialApi.search).toHaveBeenCalledTimes(1)
    expect(socialApi.search).toHaveBeenCalledWith('оле')
  })

  it('ignores an answer that arrives after a newer search', async () => {
    const first = deferred<PersonSearchResult[]>()
    vi.mocked(socialApi.search).mockReturnValueOnce(first.promise).mockResolvedValueOnce([taras])
    const { wrapper } = await mountView()

    await type(wrapper, 'ол')
    await vi.advanceTimersByTimeAsync(350)
    await type(wrapper, 'тар')
    await settle()
    first.resolve([olena])
    await flushPromises()

    expect(wrapper.text()).toContain('Тарас')
    expect(wrapper.text()).not.toContain('Олена')
  })

  it('lists people linking to their profiles', async () => {
    vi.mocked(socialApi.search).mockResolvedValue([olena, taras])
    const { wrapper } = await mountView()

    await type(wrapper, 'ол')
    await settle()

    expect(wrapper.findAll('li')).toHaveLength(2)
    expect(wrapper.get('a[href="/u/olena"]').text()).toContain('@olena')
    expect(wrapper.get('a[href="/u/olena"]').text()).toContain('Олена')
  })

  it('follows optimistically and keeps the state on success', async () => {
    const pending = deferred<null>()
    vi.mocked(socialApi.search).mockResolvedValue([{ ...olena }])
    vi.mocked(socialApi.follow).mockReturnValue(pending.promise)
    const { wrapper } = await mountView()
    await type(wrapper, 'ол')
    await settle()

    const button = wrapper.get('li button')
    expect(button.attributes('aria-pressed')).toBe('false')
    await button.trigger('click')

    expect(socialApi.follow).toHaveBeenCalledWith('olena')
    expect(button.attributes('aria-pressed')).toBe('true')
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.text()).toBe('Відписатися')

    pending.resolve(null)
    await flushPromises()
    expect(button.attributes('aria-pressed')).toBe('true')
    expect(button.attributes('disabled')).toBeUndefined()
  })

  it('unfollows, and rolls back with an error when the request fails', async () => {
    vi.mocked(socialApi.search).mockResolvedValue([{ ...taras }])
    vi.mocked(socialApi.unfollow).mockRejectedValue(new ApiError(500, null))
    const { wrapper } = await mountView()
    await type(wrapper, 'тар')
    await settle()

    await wrapper.get('li button').trigger('click')
    await flushPromises()

    expect(socialApi.unfollow).toHaveBeenCalledWith('taras')
    expect(wrapper.get('li button').attributes('aria-pressed')).toBe('true')
    expect(wrapper.get('li button').attributes('disabled')).toBeUndefined()
    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося')
  })

  it('shows the empty state', async () => {
    vi.mocked(socialApi.search).mockResolvedValue([])
    const { wrapper } = await mountView()

    await type(wrapper, 'нема')
    await settle()

    expect(wrapper.text()).toContain('Нікого не знайдено')
  })

  it('shows an error and retries', async () => {
    vi.mocked(socialApi.search).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce([olena])
    const { wrapper } = await mountView()

    await type(wrapper, 'ол')
    await settle()
    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося')

    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(socialApi.search).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Олена')
  })

  it('restores the query from the URL and searches at once', async () => {
    vi.mocked(socialApi.search).mockResolvedValue([olena])
    const { wrapper } = await mountView('/people?q=%D0%BE%D0%BB')
    await flushPromises()

    expect((wrapper.get('#people-search').element as HTMLInputElement).value).toBe('ол')
    expect(socialApi.search).toHaveBeenCalledWith('ол')
    expect(wrapper.text()).toContain('Олена')
  })

  it('keeps the query in the URL while typing', async () => {
    vi.mocked(socialApi.search).mockResolvedValue([])
    const { wrapper, router } = await mountView()

    await type(wrapper, 'ол')
    await flushPromises()

    expect(router.currentRoute.value.query.q).toBe('ол')
  })
})
