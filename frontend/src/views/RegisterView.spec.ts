import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import RegisterView from './RegisterView.vue'

function mountView() {
  const pinia = createPinia()
  setActivePinia(pinia)

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/register', name: 'register', component: RegisterView },
    ],
  })
  router.push('/register')

  const wrapper = mount(RegisterView, { global: { plugins: [pinia, router, i18n] } })
  return { wrapper, router, auth: useAuthStore() }
}

async function fillAndSubmit(wrapper: ReturnType<typeof mountView>['wrapper']) {
  await wrapper.get('#register-name').setValue('Olena')
  await wrapper.get('#email').setValue('olena@example.com')
  await wrapper.get('#password').setValue('secret-password')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

describe('RegisterView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('limits the name to 100 characters', () => {
    const { wrapper } = mountView()

    expect(wrapper.get('#register-name').attributes('maxlength')).toBe('100')
  })

  it('registers with the browser time zone and goes to the home page', async () => {
    const { wrapper, router, auth } = mountView()
    const register = vi.spyOn(auth, 'register').mockResolvedValue()

    await fillAndSubmit(wrapper)

    expect(register).toHaveBeenCalledWith({
      name: 'Olena',
      email: 'olena@example.com',
      password: 'secret-password',
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    })
    expect(router.currentRoute.value.name).toBe('home')
  })

  it('marks the email field when it is already taken', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'register').mockRejectedValue(new ApiError(422, { errors: { email: ['The email has already been taken.'] } }))

    await fillAndSubmit(wrapper)

    expect(wrapper.get('#email-error').text()).toBe('Акаунт з таким email уже існує.')
    expect(wrapper.get('#email').attributes('aria-invalid')).toBe('true')
  })

  it('marks the password field when it is too short', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'register').mockRejectedValue(new ApiError(422, { errors: { password: ['too short'] } }))

    await fillAndSubmit(wrapper)

    expect(wrapper.find('#password-error').exists()).toBe(true)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('shows the validation message of the name field as it is', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'register').mockRejectedValue(new ApiError(422, { errors: { name: ['The name field is required.'] } }))

    await fillAndSubmit(wrapper)

    expect(wrapper.get('#register-name-error').text()).toBe('The name field is required.')
  })

  it('shows a general message for an unexpected 422 and for other failures', async () => {
    const { wrapper, auth } = mountView()
    const register = vi.spyOn(auth, 'register').mockRejectedValueOnce(new ApiError(422, { errors: { timezone: ['invalid'] } }))

    await fillAndSubmit(wrapper)
    const generic = wrapper.get('[role="alert"]').text()
    expect(generic).not.toBe('')

    register.mockRejectedValueOnce(new ApiError(500, null))
    await fillAndSubmit(wrapper)
    expect(wrapper.get('[role="alert"]').text()).toBe(generic)
  })

  it('clears the previous errors when it is submitted again', async () => {
    const { wrapper, auth } = mountView()
    const register = vi.spyOn(auth, 'register').mockRejectedValueOnce(new ApiError(422, { errors: { email: ['taken'] } }))

    await fillAndSubmit(wrapper)
    expect(wrapper.find('#email-error').exists()).toBe(true)

    register.mockResolvedValueOnce()
    await fillAndSubmit(wrapper)
    expect(wrapper.find('#email-error').exists()).toBe(false)
  })
})
