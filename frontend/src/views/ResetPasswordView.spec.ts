import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { i18n } from '@/i18n'
import ResetPasswordView from './ResetPasswordView.vue'

async function mountView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/reset-password/:token', name: 'reset-password', component: ResetPasswordView, props: true },
    ],
  })
  await router.push('/reset-password/tok123?email=olena%40example.com')
  const wrapper = mount(ResetPasswordView, { props: { token: 'tok123' }, global: { plugins: [router, i18n] } })
  return { wrapper, router }
}

async function submit(wrapper: Awaited<ReturnType<typeof mountView>>['wrapper'], confirmation = 'new-password') {
  await wrapper.get('#password').setValue('new-password')
  await wrapper.get('#password-confirmation').setValue(confirmation)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

describe('ResetPasswordView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('prefills the email from the link, resets and goes to the login with a status', async () => {
    const reset = vi.spyOn(authApi, 'resetPassword').mockResolvedValue({})
    const { wrapper, router } = await mountView()
    expect((wrapper.get('#email').element as HTMLInputElement).value).toBe('olena@example.com')

    await submit(wrapper)

    expect(reset).toHaveBeenCalledWith({
      token: 'tok123',
      email: 'olena@example.com',
      password: 'new-password',
      password_confirmation: 'new-password',
    })
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.reset).toBe('1')
  })

  it('lets the reader edit the email', async () => {
    const reset = vi.spyOn(authApi, 'resetPassword').mockResolvedValue({})
    const { wrapper } = await mountView()

    await wrapper.get('#email').setValue('other@example.com')
    await submit(wrapper)

    expect(reset).toHaveBeenCalledWith(expect.objectContaining({ email: 'other@example.com' }))
  })

  it('sends one request while waiting and disables the button', async () => {
    let finish: () => void = () => undefined
    const reset = vi.spyOn(authApi, 'resetPassword').mockImplementation(() => new Promise((resolve) => (finish = () => resolve({}))))
    const { wrapper } = await mountView()

    await wrapper.get('#password').setValue('new-password')
    await wrapper.get('#password-confirmation').setValue('new-password')
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    finish()
    await flushPromises()

    expect(reset).toHaveBeenCalledTimes(1)
  })

  it('says the link is invalid when the server rejects the token', async () => {
    vi.spyOn(authApi, 'resetPassword').mockRejectedValue(new ApiError(422, { errors: { email: ['This password reset token is invalid.'] } }))
    const { wrapper, router } = await mountView()

    await submit(wrapper)

    expect(wrapper.text()).toContain('Посилання недійсне або застаріло. Запросіть нове.')
    expect(router.currentRoute.value.name).toBe('reset-password')
  })

  it('shows the password error from the server', async () => {
    vi.spyOn(authApi, 'resetPassword').mockRejectedValue(new ApiError(422, { errors: { password: ['The password field must be at least 8 characters.'] } }))
    const { wrapper } = await mountView()

    await submit(wrapper)

    expect(wrapper.text()).toContain('Пароль має містити щонайменше 8 символів.')
  })

  it('says the passwords differ when the confirmation does not match', async () => {
    vi.spyOn(authApi, 'resetPassword').mockRejectedValue(new ApiError(422, { errors: { password: ['The password field confirmation does not match.'] } }))
    const { wrapper } = await mountView()

    await submit(wrapper, 'different-password')

    expect(wrapper.text()).toContain('Паролі не збігаються.')
  })

  it('shows a throttling message and a generic one', async () => {
    const reset = vi.spyOn(authApi, 'resetPassword').mockRejectedValueOnce(new ApiError(429, null)).mockRejectedValueOnce(new TypeError('Failed to fetch'))
    const { wrapper } = await mountView()

    await submit(wrapper)
    expect(wrapper.get('[role="alert"]').text()).toBe('Забагато спроб. Спробуйте за хвилину.')

    await submit(wrapper)
    expect(wrapper.get('[role="alert"]').text()).toBe('Не вдалося виконати запит. Спробуйте ще раз.')
    expect(reset).toHaveBeenCalledTimes(2)
  })
})
