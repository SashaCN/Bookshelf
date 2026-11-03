import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import ProfileView from './ProfileView.vue'

function mountView(timezone = 'Europe/Kyiv') {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = { id: 1, name: 'Olena', email: 'olena@example.com', timezone }

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
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

    expect(update).toHaveBeenCalledWith({ name: 'Olena K.', timezone: 'Europe/Warsaw' })
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
})
