import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { router } from './index'
import { readerRoutes } from './reader'

vi.mock('@/api/auth', () => ({ authApi: { me: vi.fn(), login: vi.fn(), register: vi.fn(), logout: vi.fn() } }))

const user = { id: 1, name: 'Olena', email: 'olena@example.com', handle: 'olena', bio: null, timezone: 'Europe/Kyiv' }

describe('the routes of the reader', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    await router.replace('/login').catch(() => undefined)
  })

  it('is one full-screen route for the id of a library entry, outside of the layout with the tab bar', () => {
    expect(readerRoutes).toHaveLength(1)

    const resolved = router.resolve('/read/12')

    expect(resolved.name).toBe('read')
    expect(resolved.params).toEqual({ id: '12' })
    expect(resolved.matched).toHaveLength(1)
    expect(resolved.meta).toMatchObject({ requiresAuth: true, title: 'reader.title' })
  })

  it('does not take anything but a number for an id', () => {
    expect(router.resolve('/read/abc').name).toBe('not-found')
    expect(router.resolve('/read/').name).toBe('not-found')
  })

  it('keeps the name of every route of the app to one route (a repeated name makes the link to one lead to the other)', () => {
    const names = router.getRoutes().flatMap((route) => (route.name ? [String(route.name)] : []))

    expect(new Set(names).size).toBe(names.length)
  })

  it('opens for a signed-in reader, with the title of the screen in the browser tab', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)

    await router.push('/read/5')

    expect(router.currentRoute.value.name).toBe('read')
    expect(document.title).toBe('Читанка · Bookshelf')
  })

  it('sends a guest to the sign-in form', async () => {
    vi.mocked(authApi.me).mockRejectedValue(new ApiError(401, null))
    useAuthStore().user = null

    await router.push('/read/5')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('keeps the chapter and the quote that the address asks for', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)

    await router.push('/read/5?chapter=3&highlight=9')

    expect(router.currentRoute.value.query).toEqual({ chapter: '3', highlight: '9' })
  })
})
