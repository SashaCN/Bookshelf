import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import type { User } from '@/types/api'
import ProfileView from './ProfileView.vue'

function mountView(timezone = 'Europe/Kyiv', extra: Partial<User> = {}) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = { id: 1, name: 'Olena', email: 'olena@example.com', timezone, username: null, is_public: false, new_followers_count: 0, ...extra }

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/u/:username/followers', name: 'followers', component: { template: '<div />' } },
      { path: '/u/:username', name: 'public-profile', component: { template: '<div />' } },
      { path: '/blocks', name: 'blocks', component: { template: '<div />' } },
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

  it('links to the list of blocked readers', () => {
    const { wrapper } = mountView()

    const link = wrapper.get('[data-test="blocked-link"]')
    expect(link.text()).toBe('Заблоковані')
    expect(link.attributes('href')).toBe('/blocks')
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

    await wrapper.get('[data-test="logout"]').trigger('click')
    await flushPromises()

    expect(logout).toHaveBeenCalled()
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('reports a failed logout instead of failing silently, and stays on the page', async () => {
    const { wrapper, router, auth } = mountView()
    vi.spyOn(auth, 'logout').mockRejectedValue(new ApiError(500, null))
    await router.isReady()

    await wrapper.get('[data-test="logout"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося')
    expect(router.currentRoute.value.name).not.toBe('login')
    expect(wrapper.get('[data-test="logout"]').attributes('disabled')).toBeUndefined()
  })

  it('logs out only once when tapped twice', async () => {
    const { wrapper, auth } = mountView()
    const logout = vi.spyOn(auth, 'logout').mockReturnValue(new Promise(() => undefined))

    await wrapper.get('[data-test="logout"]').trigger('click')
    await wrapper.get('[data-test="logout"]').trigger('click')

    expect(logout).toHaveBeenCalledTimes(1)
  })

  it('takes the "saved" note away as soon as the reader edits again', async () => {
    const { wrapper, auth } = mountView()
    vi.spyOn(auth, 'updateProfile').mockResolvedValue()

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('[role="status"]').exists()).toBe(true)

    await wrapper.get('#profile-name').setValue('Olena K')

    expect(wrapper.find('[role="status"]').exists()).toBe(false)
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

      expect(wrapper.text()).toContain('вкажіть ім’я користувача')
    })

    it('hints at the username format before anything is sent', async () => {
      const { wrapper, auth } = mountView()
      const update = vi.spyOn(auth, 'updateProfile').mockResolvedValue()

      expect(wrapper.find('#profile-username-error').exists()).toBe(false)
      await wrapper.get('#profile-username').setValue('ab')

      expect(wrapper.get('#profile-username-error').text()).toContain('від 3 до 30 символів')
      expect(wrapper.get('#profile-username').attributes('aria-invalid')).toBe('true')
      expect(wrapper.get('#profile-username').attributes('aria-describedby')).toBe('profile-username-error')

      await wrapper.get('form').trigger('submit')
      expect(update).not.toHaveBeenCalled()

      await wrapper.get('#profile-username').setValue('Olena_1')
      expect(wrapper.find('#profile-username-error').exists()).toBe(false)
    })

    it('says what a public profile shows', () => {
      const { wrapper } = mountView()

      expect(wrapper.get('#profile-public-hint').text()).toContain('публічні цитати')
      expect(wrapper.get('#profile-public-hint').text()).toContain('Пошта, бібліотека й нотатки лишаються приватними')
    })

    it('shows next to the field that the username is taken', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'updateProfile').mockRejectedValue(
        new ApiError(422, { message: 'Invalid', errors: { username: ['The username has already been taken.'] } }),
      )

      await wrapper.get('#profile-username').setValue('olena')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.get('#profile-username-error').text()).toBe('Це ім’я вже зайняте. Оберіть інше.')
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)

      await wrapper.get('#profile-username').setValue('olena2')
      expect(wrapper.find('#profile-username-error').exists()).toBe(false)
    })

    it('shows the missing username next to the field', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'updateProfile').mockRejectedValue(
        new ApiError(422, { message: 'Invalid', errors: { username: ['profile.username_required'] } }),
      )

      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.get('#profile-username-error').text()).toContain('вкажіть ім’я користувача')
    })

    it('blames the name being taken when the server rejects a well-formed username', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'updateProfile').mockRejectedValue(
        new ApiError(422, { message: 'Invalid', errors: { username: ['The username format is invalid.'] } }),
      )
      await wrapper.get('#profile-username').setValue('valid_name')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.get('#profile-username-error').text()).toContain('вже зайняте')
    })

    it('disables saving while the request is on its way', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'updateProfile').mockReturnValue(new Promise(() => undefined))

      await wrapper.get('form').trigger('submit')

      expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    })
  })

  describe('new followers', () => {
    it('links to the own followers list with a plural line', () => {
      const { wrapper } = mountView('Europe/Kyiv', { username: 'olena', is_public: true, new_followers_count: 2 })

      const line = wrapper.get('[data-test="new-followers-line"]')
      expect(line.text()).toBe('У вас 2 нові підписники')
      expect(line.attributes('href')).toBe('/u/olena/followers')
    })

    it('has no line without new followers', () => {
      const { wrapper } = mountView('Europe/Kyiv', { username: 'olena', is_public: true, new_followers_count: 0 })

      expect(wrapper.find('[data-test="new-followers-line"]').exists()).toBe(false)
    })
  })

  describe('password change', () => {
    async function fill(wrapper: ReturnType<typeof mountView>['wrapper'], confirmation = 'new-password') {
      await wrapper.get('[data-test="password-toggle"]').trigger('click')
      await wrapper.get('#profile-current-password').setValue('old-password')
      await wrapper.get('#profile-new-password').setValue('new-password')
      await wrapper.get('#profile-confirm-password').setValue(confirmation)
      await wrapper.get('[data-test="password-form"]').trigger('submit')
      await flushPromises()
    }

    it('sends the three passwords, clears the fields and says it is done', async () => {
      const { wrapper, auth } = mountView()
      const change = vi.spyOn(auth, 'changePassword').mockResolvedValue()

      await fill(wrapper)

      expect(change).toHaveBeenCalledWith({ current_password: 'old-password', password: 'new-password', password_confirmation: 'new-password' })
      expect(wrapper.get('[data-test="password-done"]').text()).toContain('Пароль змінено')
    })

    it('keeps the form collapsed until opened, toggles aria-expanded and collapses after success', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'changePassword').mockResolvedValue()
      const toggle = wrapper.get('[data-test="password-toggle"]')

      expect(toggle.attributes('aria-expanded')).toBe('false')
      expect(wrapper.find('#profile-current-password').exists()).toBe(false)

      await toggle.trigger('click')
      expect(toggle.attributes('aria-expanded')).toBe('true')
      expect(wrapper.find('#profile-current-password').exists()).toBe(true)

      await toggle.trigger('click')
      expect(toggle.attributes('aria-expanded')).toBe('false')
      expect(wrapper.find('#profile-current-password').exists()).toBe(false)

      await fill(wrapper)
      expect(toggle.attributes('aria-expanded')).toBe('false')
      expect(wrapper.find('[data-test="password-form"]').exists()).toBe(false)
      expect(wrapper.get('[data-test="password-done"]').text()).toContain('Пароль змінено')
    })

    it('does not send mismatching passwords', async () => {
      const { wrapper, auth } = mountView()
      const change = vi.spyOn(auth, 'changePassword').mockResolvedValue()

      await fill(wrapper, 'different')

      expect(change).not.toHaveBeenCalled()
      expect(wrapper.get('#profile-new-password-error').text()).toBe('Паролі не збігаються.')
    })

    it('shows a wrong current password next to its field', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'changePassword').mockRejectedValue(new ApiError(422, { errors: { current_password: ['wrong'] } }))

      await fill(wrapper)

      expect(wrapper.get('#profile-current-password-error').text()).toBe('Поточний пароль невірний.')
      expect(wrapper.find('[data-test="password-done"]').exists()).toBe(false)
    })

    it('shows a refused new password next to its field', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'changePassword').mockRejectedValue(new ApiError(422, { errors: { password: ['weak'] } }))

      await fill(wrapper)

      expect(wrapper.get('#profile-new-password-error').text()).toContain('щонайменше 8')
    })

    it('shows other failures as a notice', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'changePassword').mockRejectedValue(new ApiError(500, null))

      await fill(wrapper)

      expect(wrapper.find('[data-test="password-form"] [role="alert"]').exists()).toBe(true)
    })

    it('disables the button while the request is on its way', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'changePassword').mockReturnValue(new Promise(() => undefined))

      await fill(wrapper)

      expect(wrapper.get('[data-test="password-form"] button[type="submit"]').attributes('disabled')).toBeDefined()
    })
  })

  describe('account deletion', () => {
    async function openDialog(wrapper: ReturnType<typeof mountView>['wrapper']) {
      await wrapper.get('[data-test="delete-account"]').trigger('click')
      await flushPromises()
    }

    async function confirm(wrapper: ReturnType<typeof mountView>['wrapper'], password = 'secret-password') {
      await wrapper.get('#delete-account-password').setValue(password)
      await wrapper.get('[role="dialog"] form').trigger('submit')
      await flushPromises()
    }

    it('explains what is deleted and asks for the password before anything happens', async () => {
      const { wrapper, auth } = mountView()
      const remove = vi.spyOn(auth, 'deleteAccount').mockResolvedValue()

      await openDialog(wrapper)

      const text = wrapper.get('[role="dialog"]').text()
      for (const part of ['бібліотека', 'цитати', 'відгуки', 'EPUB', 'підписники', 'неможливо скасувати']) {
        expect(text).toContain(part)
      }
      expect(remove).not.toHaveBeenCalled()
    })

    it('moves the focus into the dialog, closes on Escape and gives the focus back', async () => {
      const { wrapper } = mountView()
      wrapper.element.ownerDocument.body.append(wrapper.element)
      const opener = wrapper.get('[data-test="delete-account"]')
      ;(opener.element as HTMLElement).focus()

      await opener.trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="dialog"]').element.contains(document.activeElement)).toBe(true)

      await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })
      await flushPromises()

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(document.activeElement).toBe(opener.element)
      wrapper.unmount()
    })

    it('can be cancelled with the button without deleting anything', async () => {
      const { wrapper, auth } = mountView()
      const remove = vi.spyOn(auth, 'deleteAccount').mockResolvedValue()
      await openDialog(wrapper)

      await wrapper.get('[role="dialog"] button[type="button"]').trigger('click')

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(remove).not.toHaveBeenCalled()
    })

    it('deletes the account and goes to the login screen with a note', async () => {
      const { wrapper, router, auth } = mountView()
      const remove = vi.spyOn(auth, 'deleteAccount').mockResolvedValue()
      await openDialog(wrapper)

      await confirm(wrapper)

      expect(remove).toHaveBeenCalledWith('secret-password')
      expect(router.currentRoute.value.name).toBe('login')
      expect(router.currentRoute.value.query.deleted).toBe('1')
    })

    it('does not send an empty password', async () => {
      const { wrapper, auth } = mountView()
      const remove = vi.spyOn(auth, 'deleteAccount').mockResolvedValue()
      await openDialog(wrapper)

      await confirm(wrapper, '')

      expect(remove).not.toHaveBeenCalled()
    })

    it('keeps the dialog open and names the password when it is wrong', async () => {
      const { wrapper, router, auth } = mountView()
      vi.spyOn(auth, 'deleteAccount').mockRejectedValue(new ApiError(422, { errors: { password: ['wrong'] } }))
      await openDialog(wrapper)

      await confirm(wrapper, 'wrong')

      expect(wrapper.get('#delete-account-password-error').text()).toBe('Пароль невірний.')
      expect(router.currentRoute.value.name).not.toBe('login')
    })

    it('shows other failures inside the dialog', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'deleteAccount').mockRejectedValue(new ApiError(500, null))
      await openDialog(wrapper)

      await confirm(wrapper)

      expect(wrapper.find('[role="dialog"] [role="alert"]').exists()).toBe(true)
    })

    it('disables the confirm button and ignores Escape while deleting', async () => {
      const { wrapper, auth } = mountView()
      vi.spyOn(auth, 'deleteAccount').mockReturnValue(new Promise(() => undefined))
      await openDialog(wrapper)

      await confirm(wrapper)
      await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })

      expect(wrapper.get('[role="dialog"] button[type="submit"]').attributes('disabled')).toBeDefined()
      expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    })
  })

  describe('data export', () => {
    function stubDownload() {
      const createObjectURL = vi.fn().mockReturnValue('blob:export')
      const revokeObjectURL = vi.fn()
      Object.defineProperty(URL, 'createObjectURL', { configurable: true, value: createObjectURL })
      Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, value: revokeObjectURL })
      const downloads: { href: string; download: string }[] = []
      vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
        downloads.push({ href: this.href, download: this.download })
      })
      return { createObjectURL, revokeObjectURL, downloads }
    }

    it('explains what is in the file and that EPUB files are not', () => {
      const { wrapper } = mountView()

      expect(wrapper.get('#profile-data-hint').text()).toContain('Самих EPUB-файлів у ньому немає.')
      expect(wrapper.get('[data-test="export-data"]').text()).toBe('Завантажити мої дані')
    })

    it('sits above the danger zone', () => {
      const { wrapper } = mountView()

      const html = wrapper.html()
      expect(html.indexOf('profile-data-title')).toBeLessThan(html.indexOf('profile-danger-title'))
    })

    it('downloads the file', async () => {
      vi.useFakeTimers({ toFake: ['setTimeout'] })
      const { createObjectURL, revokeObjectURL, downloads } = stubDownload()
      const file = new Blob(['{}'])
      vi.spyOn(authApi, 'exportData').mockResolvedValue(file)
      const { wrapper } = mountView()

      await wrapper.get('[data-test="export-data"]').trigger('click')
      await flushPromises()

      expect(createObjectURL).toHaveBeenCalledWith(file)
      expect(downloads).toHaveLength(1)
      expect(downloads[0]?.download).toMatch(/^bookshelf-export-\d{4}-\d{2}-\d{2}\.json$/)
      expect(document.querySelector('a[download]')).toBeNull()
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)

      vi.runAllTimers()
      expect(revokeObjectURL).toHaveBeenCalledWith('blob:export')
      vi.useRealTimers()
    })

    it('disables the button while the file is being prepared', async () => {
      stubDownload()
      let finish: (file: Blob) => void = () => undefined
      vi.spyOn(authApi, 'exportData').mockReturnValue(new Promise((resolve) => (finish = resolve)))
      const { wrapper } = mountView()

      await wrapper.get('[data-test="export-data"]').trigger('click')

      expect(wrapper.get('[data-test="export-data"]').attributes('disabled')).toBeDefined()
      expect(wrapper.get('[data-test="export-data"]').text()).toBe('Готуємо файл…')

      finish(new Blob([]))
      await flushPromises()

      expect(wrapper.get('[data-test="export-data"]').attributes('disabled')).toBeUndefined()
    })

    it('shows an error notice when the download fails', async () => {
      const { downloads } = stubDownload()
      vi.spyOn(authApi, 'exportData').mockRejectedValue(new ApiError(500, null))
      const { wrapper } = mountView()

      await wrapper.get('[data-test="export-data"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(true)
      expect(downloads).toEqual([])
      expect(wrapper.get('[data-test="export-data"]').attributes('disabled')).toBeUndefined()
    })

    it('says the limit is per hour when it is reached', async () => {
      stubDownload()
      vi.spyOn(authApi, 'exportData').mockRejectedValue(new ApiError(429, null))
      const { wrapper } = mountView()

      await wrapper.get('[data-test="export-data"]').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('тричі на годину')
    })
  })
})
