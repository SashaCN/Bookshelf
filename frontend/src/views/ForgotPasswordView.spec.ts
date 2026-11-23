import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { i18n } from '@/i18n'
import ForgotPasswordView from './ForgotPasswordView.vue'

async function mountView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView },
    ],
  })
  await router.push('/forgot-password')
  return mount(ForgotPasswordView, { global: { plugins: [router, i18n] } })
}

async function submit(wrapper: Awaited<ReturnType<typeof mountView>>) {
  await wrapper.get('#email').setValue('olena@example.com')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

describe('ForgotPasswordView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('asks for the link and shows the neutral confirmation', async () => {
    const forgot = vi.spyOn(authApi, 'forgotPassword').mockResolvedValue({})
    const wrapper = await mountView()

    await submit(wrapper)

    expect(forgot).toHaveBeenCalledWith('olena@example.com')
    expect(wrapper.get('[data-test="reset-link-sent"]').text()).toBe('Якщо такий акаунт існує, ми надіслали лист із посиланням')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('sends one request while waiting and disables the button', async () => {
    let finish: () => void = () => undefined
    const forgot = vi.spyOn(authApi, 'forgotPassword').mockImplementation(() => new Promise((resolve) => (finish = () => resolve({}))))
    const wrapper = await mountView()

    await wrapper.get('#email').setValue('olena@example.com')
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    finish()
    await flushPromises()

    expect(forgot).toHaveBeenCalledTimes(1)
  })

  it('shows the field error for a malformed email', async () => {
    vi.spyOn(authApi, 'forgotPassword').mockRejectedValue(new ApiError(422, { errors: { email: ['The email field must be a valid email address.'] } }))
    const wrapper = await mountView()

    await submit(wrapper)

    expect(wrapper.text()).toContain('The email field must be a valid email address.')
    expect(wrapper.find('[data-test="reset-link-sent"]').exists()).toBe(false)
  })

  it('shows a throttling message', async () => {
    vi.spyOn(authApi, 'forgotPassword').mockRejectedValue(new ApiError(429, null))
    const wrapper = await mountView()

    await submit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Забагато спроб. Спробуйте за хвилину.')
  })

  it('shows a generic message when the server cannot be reached', async () => {
    vi.spyOn(authApi, 'forgotPassword').mockRejectedValue(new TypeError('Failed to fetch'))
    const wrapper = await mountView()

    await submit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Не вдалося виконати запит. Спробуйте ще раз.')
  })
})
