import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import type { User } from '@/types/api'
import ProfileView from './ProfileView.vue'

function mountView(timezone = 'Europe/Kyiv', extra: Partial<User> = {}) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = { id: 1, name: 'Olena', email: 'olena@example.com', timezone, username: null, is_public: false, ...extra }

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/u/:username', name: 'public-profile', component: { template: '<div />' } },
      { path: '/profile', name: 'profile', component: ProfileView },
    ],
  })
  router.push('/profile')

  const wrapper = mount(ProfileView, { global: { plugins: [pinia, router, i18n] } })
  return { wrapper, router, auth }
}

describe('ProfileView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('shows the reader with the saved name, e-mail and time zone', () => {
    const { wrapper } = mountView('Europe/Warsaw')

    expect((wrapper.get('#profile-name').element as HTMLInputElement).value).toBe('Olena')
    expect(wrapper.text()).toContain('olena@example.com')
    expect((wrapper.get('#profile-timezone').element as HTMLSelectElement).value).toBe('Europe/Warsaw')
  })

  it('offers a time zone the browser list does not know, so it is not lost', () => {
    const { wrapper } = mountView('Europe/Nowhere')

    const options = wrapper.findAll('#profile-timezone option').map((option) => option.text())

    expect(options[0]).toBe('Europe/Nowhere')
  })

  it('saves the trimmed name and the chosen time zone', async () => {
    const { wrapper, auth } = mountView()
    const update = vi.spyOn(auth, 'updateProfile').mockResolvedValue()

    await wrapper.get('#profile-name').setValue('  Olena K.  ')
    await wrapper.get('#profile-timezone').setValue('Europe/Warsaw')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(update).toHaveBeenCalledWith({ name: 'Olena K.', timezone: 'Europe/Warsaw', username: null, is_public: false })
    expect(wrapper.get('[role="status"]').text()).toBe('Збережено.')
  })

  it('shows an error and no confirmation when saving fails', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'updateProfile').mockRejectedValue(new ApiError(500, null))

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Не вдалося')
  })

  it('logs out and goes to the login page', async () => {
    const { wrapper, router, auth } = mountView()
    const logout = vi.spyOn(auth, 'logout').mockResolvedValue()

    await wrapper.get('button[type="button"]').trigger('click')
    await flushPromises()

    expect(logout).toHaveBeenCalled()
    expect(router.currentRoute.value.name).toBe('login')
  })

  describe('public profile', () => {
    it('shows the saved username and the toggle', () => {
      const { wrapper } = mountView('Europe/Kyiv', { username: 'olena', is_public: true })

      expect((wrapper.get('#profile-username').element as HTMLInputElement).value).toBe('olena')
      expect((wrapper.get('#profile-public').element as HTMLInputElement).checked).toBe(true)
    })

    it('saves the username and the toggle with the rest of the profile', async () => {
      const { wrapper, auth } = mountView()
      const update = vi.spyOn(auth, 'updateProfile').mockResolvedValue()

      await wrapper.get('#profile-username').setValue(' Olena_K ')
      await wrapper.get('#profile-public').setValue(true)
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(update).toHaveBeenCalledWith({ name: 'Olena', timezone: 'Europe/Kyiv', username: 'olena_k', is_public: true })
    })

    it('links to the own public page only when it is set and public', () => {
      const hidden = mountView('Europe/Kyiv', { username: 'olena', is_public: false })
      expect(hidden.wrapper.find('a[href="/u/olena"]').exists()).toBe(false)

      const noName = mountView('Europe/Kyiv', { username: null, is_public: true })
      expect(noName.wrapper.find('a[href^="/u/"]').exists()).toBe(false)

      const shown = mountView('Europe/Kyiv', { username: 'olena', is_public: true })
      expect(shown.wrapper.get('a[href="/u/olena"]').text()).toBe('Моя публічна сторінка')
    })

    it('explains that a username is needed to go public', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'updateProfile').mockRejectedValue(
        new ApiError(422, { message: 'Invalid', errors: { username: ['profile.username_required'] } }),
      )

      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.text()).toContain('вкажіть імʼя користувача')
    })
  })
})
