import { flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { http, onUnauthorized } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { useLibraryStore } from '@/stores/library'
import { createTestRouter } from '@/test/utils'
import { installSessionExpiry } from './session'

const user = { id: 1, name: 'Olena', email: 'olena@example.com', timezone: 'Europe/Kyiv', username: null, is_public: false }

describe('session expiry', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.stubGlobal('fetch', vi.fn(async () => new Response(null, { status: 401 })))
  })

  afterEach(() => {
    onUnauthorized(null)
    vi.unstubAllGlobals()
  })

  async function setUp(path: string) {
    const router = createTestRouter()
    await router.push(path)
    installSessionExpiry(router)
    return router
  }

  it('signs the reader out, clears their data and returns to the login form remembering the page', async () => {
    const router = await setUp('/library/7')
    const auth = useAuthStore()
    auth.user = user
    useLibraryStore().loaded = true

    await http.get('/api/library').catch(() => undefined)
    await flushPromises()

    expect(auth.isAuthenticated).toBe(false)
    expect(useLibraryStore().loaded).toBe(false)
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/library/7')
  })

  it('ignores a 401 for a visitor who is not signed in', async () => {
    const router = await setUp('/library')

    await http.get('/api/me').catch(() => undefined)
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('library')
  })
})
