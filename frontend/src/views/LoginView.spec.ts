import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ApiError } from '@/api/http'
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
      { path: '/forgot-password', name: 'forgot-password', component: { template: '<div />' } },
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

  it('returns to the page the reader was sent away from', async () => {
    const { wrapper, router, auth } = mountView()
    vi.spyOn(auth, 'login').mockResolvedValue()
    await router.replace('/login?redirect=%2Fstats')
    router.addRoute({ path: '/stats', name: 'stats', component: { template: '<div />' } })

    await fillAndSubmit(wrapper)

    expect(router.currentRoute.value.fullPath).toBe('/stats')
  })

  it('sends one request when submitted twice while waiting', async () => {
    const { wrapper, auth } = mountView()
    let finish: () => void = () => undefined
    const login = vi.spyOn(auth, 'login').mockImplementation(() => new Promise<void>((resolve) => (finish = resolve)))

    await wrapper.get('#email').setValue('olena@example.com')
    await wrapper.get('#password').setValue('secret-password')
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')
    finish()
    await flushPromises()

    expect(login).toHaveBeenCalledTimes(1)
  })

  it('shows a localized message for wrong credentials', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'login').mockRejectedValue(new ApiError(422, { errors: { email: ['These credentials do not match.'] } }))

    await fillAndSubmit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Невірний email або пароль.')
  })

  it('shows a generic message when the server cannot be reached', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'login').mockRejectedValue(new TypeError('Failed to fetch'))

    await fillAndSubmit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Не вдалося виконати запит. Спробуйте ще раз.')
    expect((wrapper.get('#email').element as HTMLInputElement).value).toBe('olena@example.com')
  })

  it('shows a throttling message after too many attempts', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'login').mockRejectedValue(new ApiError(429, null))

    await fillAndSubmit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Забагато спроб. Спробуйте за хвилину.')
  })

  it('says the account was deleted after coming from the deletion', async () => {
    const { wrapper, router } = mountView()
    await router.push('/login?deleted=1')

    expect(wrapper.get('[data-test="account-deleted"]').text()).toBe('Акаунт видалено')
  })

  it('says the password was changed after a reset', async () => {
    const { wrapper, router } = mountView()
    await router.push('/login?reset=1')

    expect(wrapper.get('[data-test="password-changed"]').text()).toBe('Пароль змінено, увійдіть з новим паролем')
  })

  it('links to the password reset', () => {
    const { wrapper } = mountView()

    expect(wrapper.text()).toContain('Забули пароль?')
  })

  it('has no such note on a normal visit', () => {
    const { wrapper } = mountView()

    expect(wrapper.find('[data-test="account-deleted"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="password-changed"]').exists()).toBe(false)
  })
})
