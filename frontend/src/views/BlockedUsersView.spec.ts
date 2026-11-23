import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { createTestEnvironment } from '@/test/utils'
import BlockedUsersView from './BlockedUsersView.vue'

vi.mock('@/api/social', () => ({ socialApi: { blocked: vi.fn(), unblock: vi.fn() } }))

const people = [
  { username: 'ivan', name: 'Іван' },
  { username: 'olena', name: 'Олена' },
]

async function mountView(data = people, next: string | null = null, path = '/blocks') {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(socialApi.blocked).mockResolvedValue({ data: [...data], links: { next } })
  await router.push(path)

  const wrapper = mount(BlockedUsersView, { global: { plugins } })
  await flushPromises()
  return { wrapper }
}

const unblockButton = (wrapper: ReturnType<typeof mount>, username: string) =>
  wrapper.get(`button[aria-label="Розблокувати @${username}"]`)

describe('BlockedUsersView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('lists the blocked readers with an unblock button each', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.get('h1').text()).toBe('Заблоковані')
    expect(wrapper.text()).toContain('@ivan')
    expect(wrapper.text()).toContain('Олена')
    expect(wrapper.findAll('button').filter((b) => b.text() === 'Розблокувати')).toHaveLength(2)
    expect(wrapper.get('[aria-label="Назад"]').attributes('href')).toBe('/profile')
  })

  it('shows an empty state', async () => {
    const { wrapper } = await mountView([])

    expect(wrapper.text()).toContain('Нікого не заблоковано')
  })

  it('confirms a fresh block with a status', async () => {
    const { wrapper } = await mountView(people, null, '/blocks?blocked=1')

    expect(wrapper.get('[role="status"]').text()).toBe('Читача заблоковано.')
  })

  it('takes the row away at once and keeps it away when the server agrees', async () => {
    let finish!: () => void
    vi.mocked(socialApi.unblock).mockReturnValue(new Promise((resolve) => (finish = () => resolve(null))))
    const { wrapper } = await mountView()

    await unblockButton(wrapper, 'ivan').trigger('click')

    expect(socialApi.unblock).toHaveBeenCalledWith('ivan')
    expect(wrapper.text()).not.toContain('@ivan')

    finish()
    await flushPromises()
    expect(wrapper.text()).not.toContain('@ivan')
    expect(wrapper.text()).toContain('@olena')
  })

  it('puts the row back where it was and says so when the server refuses', async () => {
    vi.mocked(socialApi.unblock).mockRejectedValue(new ApiError(500, null))
    const { wrapper } = await mountView()

    await unblockButton(wrapper, 'ivan').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Не вдалося')
    const rows = wrapper.findAll('li').map((li) => li.text())
    expect(rows[0]).toContain('@ivan')
    expect(rows[1]).toContain('@olena')
  })

  it('disables the button of a row while its request is pending', async () => {
    vi.mocked(socialApi.unblock).mockReturnValue(new Promise(() => {}))
    const { wrapper } = await mountView()

    await unblockButton(wrapper, 'ivan').trigger('click')

    expect(unblockButton(wrapper, 'olena').attributes('disabled')).toBeUndefined()
    expect(socialApi.unblock).toHaveBeenCalledTimes(1)
  })

  it('offers a retry when the list cannot be loaded', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(socialApi.blocked).mockRejectedValue(new ApiError(500, null))
    await router.push('/blocks')
    const wrapper = mount(BlockedUsersView, { global: { plugins } })
    await flushPromises()

    expect(wrapper.text()).toContain('Не вдалося')

    vi.mocked(socialApi.blocked).mockResolvedValue({ data: [...people], links: { next: null } })
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('@ivan')
  })

  it('loads more', async () => {
    const { wrapper } = await mountView([people[0]], '/n')
    vi.mocked(socialApi.blocked).mockResolvedValue({ data: [people[1]], links: { next: null } })

    await wrapper.findAll('button').find((b) => b.text().includes('Показати'))!.trigger('click')
    await flushPromises()

    expect(socialApi.blocked).toHaveBeenLastCalledWith(2)
    expect(wrapper.text()).toContain('@olena')
  })
})
