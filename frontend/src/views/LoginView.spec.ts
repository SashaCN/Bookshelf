import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ApiError, NetworkError } from '@/api/http'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import LoginView from './LoginView.vue'

function mountView() {
  const pinia = createPinia()
  setActivePinia(pinia)

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: LoginView },
      { path: '/register', name: 'register', component: { template: '<div />' } },
    ],
  })
  router.push('/login')

  const wrapper = mount(LoginView, { global: { plugins: [pinia, router, i18n] } })
  return { wrapper, router, auth: useAuthStore() }
}

async function fillAndSubmit(wrapper: ReturnType<typeof mountView>['wrapper']) {
  await wrapper.get('#email').setValue('olena@example.com')
  await wrapper.get('#password').setValue('secret-password')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

describe('LoginView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('logs in and goes to the home page', async () => {
    const { wrapper, router, auth } = mountView()
    const login = vi.spyOn(auth, 'login').mockResolvedValue()

    await fillAndSubmit(wrapper)

    expect(login).toHaveBeenCalledWith({ email: 'olena@example.com', password: 'secret-password' })
    expect(router.currentRoute.value.name).toBe('home')
  })

  it('shows a localized message for wrong credentials', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'login').mockRejectedValue(new ApiError(422, { errors: { email: ['These credentials do not match.'] } }))

    await fillAndSubmit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Невірний email або пароль.')
  })

  it('shows a throttling message after too many attempts', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'login').mockRejectedValue(new ApiError(429, null))

    await fillAndSubmit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Забагато спроб. Спробуйте за хвилину.')
  })

  it('explains why the reader is asked to sign in again after the session ended', async () => {
    const { wrapper, router } = mountView()
    await router.push('/login?expired=1')
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toBe('Сесія закінчилась. Увійдіть ще раз.')
  })

  it('says nothing about a session when the reader just opened the form', async () => {
    const { wrapper, router } = mountView()
    await router.isReady()

    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })

  it('blames the connection, not the password, when the server cannot be reached', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'login').mockRejectedValue(new NetworkError())

    await fillAndSubmit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toContain('Немає зв’язку із сервером')
  })
})
