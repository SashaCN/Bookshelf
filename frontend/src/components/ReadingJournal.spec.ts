import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { createTestEnvironment } from '@/test/utils'
import type { ReadingLog } from '@/types/api'
import ReadingJournal from './ReadingJournal.vue'

vi.mock('@/api/library', () => ({ libraryApi: { logs: vi.fn() } }))

function makeLog(overrides: Partial<ReadingLog> = {}): ReadingLog {
  return {
    id: 1,
    from_page: 0,
    to_page: 20,
    pages: 20,
    logged_on: '2026-10-05',
    created_at: '2026-10-05T10:00:00+00:00',
    ...overrides,
  }
}

function mountJournal(version = 0) {
  const { plugins } = createTestEnvironment()
  return mount(ReadingJournal, { props: { bookId: 7, version }, global: { plugins } })
}

describe('ReadingJournal', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('lists each move with its day, its pages and its range', async () => {
    vi.mocked(libraryApi.logs).mockResolvedValue({
      data: [makeLog({ id: 2, from_page: 20, to_page: 38, pages: 18, logged_on: '2026-10-05' }), makeLog({ id: 1 })],
      links: { next: null },
    })

    const wrapper = mountJournal()
    await flushPromises()

    expect(libraryApi.logs).toHaveBeenCalledWith(7, 1)
    expect(wrapper.findAll('li')).toHaveLength(2)
    expect(wrapper.text()).toContain('5 жовтня')
    expect(wrapper.text()).toContain('+18 стор.')
    expect(wrapper.text()).toContain('з 20 до 38')
  })

  it('marks a correction downwards with a minus', async () => {
    vi.mocked(libraryApi.logs).mockResolvedValue({
      data: [makeLog({ from_page: 300, to_page: 30, pages: -270 })],
      links: { next: null },
    })

    const wrapper = mountJournal()
    await flushPromises()

    expect(wrapper.text()).toContain('−270 стор.')
  })

  it('says so when nothing has been logged yet', async () => {
    vi.mocked(libraryApi.logs).mockResolvedValue({ data: [], links: { next: null } })

    const wrapper = mountJournal()
    await flushPromises()

    expect(wrapper.text()).toContain('Записів поки немає')
    expect(wrapper.find('ul').exists()).toBe(false)
  })

  it('loads more entries on demand and hides the button after the last page', async () => {
    vi.mocked(libraryApi.logs)
      .mockResolvedValueOnce({ data: [makeLog({ id: 2 })], links: { next: '/api/library/7/logs?page=2' } })
      .mockResolvedValueOnce({ data: [makeLog({ id: 1 })], links: { next: null } })

    const wrapper = mountJournal()
    await flushPromises()
    expect(wrapper.findAll('li')).toHaveLength(1)

    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(libraryApi.logs).toHaveBeenLastCalledWith(7, 2)
    expect(wrapper.findAll('li')).toHaveLength(2)
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('loads again when its version changes', async () => {
    vi.mocked(libraryApi.logs).mockResolvedValue({ data: [], links: { next: null } })

    const wrapper = mountJournal(0)
    await flushPromises()
    await wrapper.setProps({ version: 1 })
    await flushPromises()

    expect(libraryApi.logs).toHaveBeenCalledTimes(2)
    expect(libraryApi.logs).toHaveBeenLastCalledWith(7, 1)
  })

  it('shows an error and can retry', async () => {
    vi.mocked(libraryApi.logs)
      .mockRejectedValueOnce(new ApiError(500, null))
      .mockResolvedValueOnce({ data: [makeLog()], links: { next: null } })

    const wrapper = mountJournal()
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.findAll('li')).toHaveLength(1)
  })
})
