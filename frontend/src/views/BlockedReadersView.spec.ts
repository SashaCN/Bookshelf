import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { useFeedStore } from '@/stores/feed'
import { makeReader } from '@/test/socialFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Reader, ReadersPage } from '@/types/social'
import BlockedReadersView from './BlockedReadersView.vue'

vi.mock('@/api/social', () => ({
  socialApi: { blocks: vi.fn(), unblock: vi.fn(), feed: vi.fn() },
}))

function page(readers: Reader[], next: string | null = null): ReadersPage {
  return { data: readers, links: { next } }
}

async function mountView(firstPage: ReadersPage = page([makeReader({ handle: 'taras_b', name: 'Taras Bondar' })])) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(socialApi.blocks).mockResolvedValue(firstPage)
  await router.push({ name: 'community-blocked' })

  const wrapper = mount(BlockedReadersView, { global: { plugins } })
  await flushPromises()

  return { wrapper }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('BlockedReadersView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('lists the readers the reader has blocked, with links to their profiles', async () => {
    const { wrapper } = await mountView(page([makeReader({ handle: 'taras_b', name: 'Taras Bondar' }), makeReader({ handle: 'ivan_k', name: 'Ivan K' })]))

    const items = wrapper.findAll('li')

    expect(items).toHaveLength(2)
    expect(items[0]!.text()).toContain('Taras Bondar')
    expect(items[0]!.text()).toContain('@taras_b')
    expect(items[0]!.get('a').attributes('href')).toBe('/u/taras_b')
  })

  it('explains what blocking means', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Ви не бачите публікацій цих читачів, а вони ваших.')
  })

  it('shows a loading note until the list arrives', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(socialApi.blocks).mockReturnValue(new Promise(() => {}))
    await router.push({ name: 'community-blocked' })

    const wrapper = mount(BlockedReadersView, { global: { plugins } })
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toContain('Завантаження…')
  })

  it('says the list is empty', async () => {
    const { wrapper } = await mountView(page([]))

    expect(wrapper.text()).toContain('Список порожній')
    expect(wrapper.text()).toContain('Ви нікого не блокували.')
  })

  it('says why and offers a retry when the list cannot be loaded', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(socialApi.blocks).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(page([makeReader()]))
    await router.push({ name: 'community-blocked' })
    const wrapper = mount(BlockedReadersView, { global: { plugins } })
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

    await buttonWithText(wrapper, 'Повторити').trigger('click')
    await flushPromises()

    expect(wrapper.findAll('li')).toHaveLength(1)
  })

  describe('unblocking', () => {
    it('removes the reader from the list, and the feed will be loaded again', async () => {
      const { wrapper } = await mountView(page([makeReader({ handle: 'taras_b', name: 'Taras Bondar' }), makeReader({ handle: 'ivan_k', name: 'Ivan K' })]))
      vi.mocked(socialApi.unblock).mockResolvedValue(null)
      vi.mocked(socialApi.feed).mockResolvedValue({ data: [], links: { next: null } })
      await useFeedStore().load('all')

      await wrapper.get('[aria-label="Розблокувати Taras Bondar"]').trigger('click')
      await flushPromises()

      expect(socialApi.unblock).toHaveBeenCalledWith('taras_b')
      expect(wrapper.findAll('li')).toHaveLength(1)
      expect(wrapper.text()).not.toContain('Taras Bondar')
      expect(useFeedStore().loaded).toBe(false)
    })

    it('keeps the reader and says why when it fails', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.unblock).mockRejectedValue(new ApiError(500, null))

      await buttonWithText(wrapper, 'Розблокувати').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('li')).toHaveLength(1)
      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
      expect(buttonWithText(wrapper, 'Розблокувати').attributes('disabled')).toBeUndefined()
    })

    it('cannot be tapped again while it is on its way', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.unblock).mockReturnValue(new Promise(() => {}))

      await buttonWithText(wrapper, 'Розблокувати').trigger('click')

      expect(buttonWithText(wrapper, 'Розблокувати').attributes('disabled')).toBeDefined()
    })
  })

  describe('paging', () => {
    it('adds the next page below on "show more"', async () => {
      const { wrapper } = await mountView(page([makeReader({ handle: 'a_reader' })], '/next'))
      vi.mocked(socialApi.blocks).mockResolvedValue(page([makeReader({ handle: 'b_reader' })]))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(socialApi.blocks).toHaveBeenLastCalledWith(2)
      expect(wrapper.findAll('li')).toHaveLength(2)
      expect(wrapper.findAll('button').some((button) => button.text() === 'Показати ще')).toBe(false)
    })

    it('keeps the readers and offers a retry when the next page fails', async () => {
      const { wrapper } = await mountView(page([makeReader({ handle: 'a_reader' })], '/next'))
      vi.mocked(socialApi.blocks).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(page([makeReader({ handle: 'b_reader' })]))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()
      expect(wrapper.findAll('li')).toHaveLength(1)

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.findAll('li')).toHaveLength(2)
    })
  })
})
