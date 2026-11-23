import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { createTestEnvironment } from '@/test/utils'
import type { User } from '@/types/api'
import ProfileView from './ProfileView.vue'

vi.mock('@/api/auth', () => ({
  authApi: { exportData: vi.fn(), me: vi.fn(), updateMe: vi.fn(), login: vi.fn(), register: vi.fn(), logout: vi.fn() },
}))

const user: User = {
  id: 1,
  name: 'Olena',
  email: 'olena@example.com',
  handle: 'olena',
  bio: 'I read on trains.',
  timezone: 'Europe/Kyiv',
}

async function mountView(current: User = user) {
  const { plugins, router } = createTestEnvironment()
  useAuthStore().user = current
  await router.push({ name: 'profile' })

  const wrapper = mount(ProfileView, { global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

async function save(wrapper: ReturnType<typeof mount>) {
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

describe('ProfileView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  describe('the fields', () => {
    it('start from what the reader has now', async () => {
      const { wrapper } = await mountView()

      expect((wrapper.get('#profile-name').element as HTMLInputElement).value).toBe('Olena')
      expect((wrapper.get('#profile-handle').element as HTMLInputElement).value).toBe('olena')
      expect((wrapper.get('#profile-bio').element as HTMLTextAreaElement).value).toBe('I read on trains.')
      expect(wrapper.text()).toContain('olena@example.com')
    })

    it('show the version of the app at the bottom, taken from package.json', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.get('[data-test="app-version"]').text()).toBe(`Версія ${__APP_VERSION__}`)
      expect(__APP_VERSION__).toBe('2.0.0')
    })

    it('limit the name to 100 characters', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.get('#profile-name').attributes('maxlength')).toBe('100')
    })

    it('are labelled', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.get('label[for="profile-handle"]').text()).toBe('Нікнейм')
      expect(wrapper.get('label[for="profile-bio"]').text()).toBe('Про себе')
    })

    it('explain what is public and what never is', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.text()).toContain('Нікнейм, ім’я та опис видно іншим читачам. Email і часовий пояс ніколи.')
      expect(wrapper.get('#profile-handle-hint').text()).toContain('від 3 до 30 малих латинських літер')
    })

    it('count the characters of the bio, up to 280', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.get('#profile-bio-hint').text()).toContain('17 з 280')
      expect(wrapper.get('#profile-bio').attributes('maxlength')).toBe('280')

      await wrapper.get('#profile-bio').setValue('abc')
      expect(wrapper.get('#profile-bio-hint').text()).toContain('3 з 280')
    })

    it('allow for a reader without a bio', async () => {
      const { wrapper } = await mountView({ ...user, bio: null })

      expect((wrapper.get('#profile-bio').element as HTMLTextAreaElement).value).toBe('')
    })
  })

  describe('saving', () => {
    it('sends the name, the time zone, the handle and the bio', async () => {
      const { wrapper } = await mountView()
      vi.mocked(authApi.updateMe).mockResolvedValue({ ...user, handle: 'book_worm', bio: 'New bio' })

      await wrapper.get('#profile-handle').setValue('  book_worm ')
      await wrapper.get('#profile-bio').setValue('  New bio ')
      await save(wrapper)

      expect(authApi.updateMe).toHaveBeenCalledWith({ name: 'Olena', timezone: 'Europe/Kyiv', handle: 'book_worm', bio: 'New bio' })
      expect(wrapper.get('[role="status"]').text()).toBe('Збережено.')
      expect(useAuthStore().user?.handle).toBe('book_worm')
    })

    it('sends no bio for an empty one, which clears it', async () => {
      const { wrapper } = await mountView()
      vi.mocked(authApi.updateMe).mockResolvedValue({ ...user, bio: null })

      await wrapper.get('#profile-bio').setValue('   ')
      await save(wrapper)

      expect(authApi.updateMe).toHaveBeenCalledWith(expect.objectContaining({ bio: null }))
    })

    it('shows the handle the way the server keeps it, in lower case', async () => {
      const { wrapper } = await mountView()
      vi.mocked(authApi.updateMe).mockResolvedValue({ ...user, handle: 'book_worm' })

      await wrapper.get('#profile-handle').setValue('Book_Worm')
      await save(wrapper)

      expect((wrapper.get('#profile-handle').element as HTMLInputElement).value).toBe('book_worm')
    })

    it('says that the handle is taken, next to the field', async () => {
      const { wrapper } = await mountView()
      vi.mocked(authApi.updateMe).mockRejectedValue(new ApiError(422, { errors: { handle: ['social.handle_taken'] } }))

      await save(wrapper)

      expect(wrapper.get('#profile-handle-error').text()).toBe('Цей нікнейм уже зайнятий.')
      expect(wrapper.get('#profile-handle').attributes('aria-invalid')).toBe('true')
      expect(wrapper.find('[role="status"]').exists()).toBe(false)
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    })

    it('says what a handle looks like when the shape is wrong', async () => {
      const { wrapper } = await mountView()
      vi.mocked(authApi.updateMe).mockRejectedValue(new ApiError(422, { errors: { handle: ['social.handle_invalid'] } }))

      await save(wrapper)

      expect(wrapper.get('#profile-handle-error').text()).toContain('3-30 малих латинських літер')
    })

    it('falls back to a general rule for a handle refused without a code', async () => {
      const { wrapper } = await mountView()
      vi.mocked(authApi.updateMe).mockRejectedValue(new ApiError(422, { errors: { handle: ['The handle field is required.'] } }))

      await save(wrapper)

      expect(wrapper.get('#profile-handle-error').text()).toBe('Нікнейм: від 3 до 30 малих латинських літер, цифр або «_».')
    })

    it('says that the bio is too long, next to the field', async () => {
      const { wrapper } = await mountView()
      vi.mocked(authApi.updateMe).mockRejectedValue(new ApiError(422, { errors: { bio: ['The bio field must not be greater than 280 characters.'] } }))

      await save(wrapper)

      expect(wrapper.get('#profile-bio-error').text()).toBe('Опис має бути не довшим за 280 символів.')
      expect(wrapper.get('#profile-bio').attributes('aria-invalid')).toBe('true')
    })

    it('forgets an error once the reader tries again', async () => {
      const { wrapper } = await mountView()
      vi.mocked(authApi.updateMe).mockRejectedValueOnce(new ApiError(422, { errors: { handle: ['social.handle_taken'] } })).mockResolvedValueOnce(user)

      await save(wrapper)
      await save(wrapper)

      expect(wrapper.find('#profile-handle-error').exists()).toBe(false)
      expect(wrapper.get('[role="status"]').text()).toBe('Збережено.')
    })

    it('says why when the server turns the change down for another reason', async () => {
      const { wrapper } = await mountView()
      vi.mocked(authApi.updateMe).mockRejectedValue(new ApiError(500, null))

      await save(wrapper)

      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
      expect(wrapper.find('#profile-handle-error').exists()).toBe(false)
    })
  })

  describe('the community', () => {
    it('links to the list of blocked readers', async () => {
      const { wrapper } = await mountView()

      const link = wrapper.findAll('a').find((candidate) => candidate.text() === 'Заблоковані читачі')

      expect(link?.attributes('href')).toBe('/community/blocked')
    })

    it('links to the public profile of the reader', async () => {
      const { wrapper } = await mountView()

      const link = wrapper.findAll('a').find((candidate) => candidate.text() === 'Мій публічний профіль')

      expect(link?.attributes('href')).toBe('/u/olena')
    })
  })

  it('signs out and goes to the sign-in screen', async () => {
    const { wrapper, router } = await mountView()
    vi.mocked(authApi.logout).mockResolvedValue({})

    await wrapper.findAll('button').find((button) => button.text() === 'Вийти')!.trigger('click')
    await flushPromises()

    expect(authApi.logout).toHaveBeenCalled()
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('stays on the profile and says so when signing out fails', async () => {
    const { wrapper, router } = await mountView()
    vi.mocked(authApi.logout).mockRejectedValue(new ApiError(500, null))

    await wrapper.findAll('button').find((button) => button.text() === 'Вийти')!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Не вдалося виконати запит')
    expect(router.currentRoute.value.name).not.toBe('login')
    expect(wrapper.findAll('button').find((button) => button.text() === 'Вийти')!.attributes('disabled')).toBeUndefined()
  })

  describe('password change', () => {
    async function fill(wrapper: ReturnType<typeof mount>, confirmation = 'new-password') {
      await wrapper.get('#profile-current-password').setValue('old-password')
      await wrapper.get('#profile-new-password').setValue('new-password')
      await wrapper.get('#profile-confirm-password').setValue(confirmation)
      await wrapper.get('[data-test="password-form"]').trigger('submit')
      await flushPromises()
    }

    it('sends the three passwords, clears the fields and says it is done', async () => {
      const { wrapper } = await mountView()
      const auth = useAuthStore()
      const change = vi.spyOn(auth, 'changePassword').mockResolvedValue()

      await fill(wrapper)

      expect(change).toHaveBeenCalledWith({ current_password: 'old-password', password: 'new-password', password_confirmation: 'new-password' })
      expect((wrapper.get('#profile-current-password').element as HTMLInputElement).value).toBe('')
      expect(wrapper.get('[data-test="password-form"] [role="status"]').text()).toContain('Пароль змінено')
    })

    it('does not send mismatching passwords', async () => {
      const { wrapper } = await mountView()
      const auth = useAuthStore()
      const change = vi.spyOn(auth, 'changePassword').mockResolvedValue()

      await fill(wrapper, 'different')

      expect(change).not.toHaveBeenCalled()
      expect(wrapper.get('#profile-new-password-error').text()).toBe('Паролі не збігаються.')
    })

    it('shows a wrong current password next to its field', async () => {
      const { wrapper } = await mountView()
      const auth = useAuthStore()
      vi.spyOn(auth, 'changePassword').mockRejectedValue(new ApiError(422, { errors: { current_password: ['wrong'] } }))

      await fill(wrapper)

      expect(wrapper.get('#profile-current-password-error').text()).toBe('Поточний пароль невірний.')
      expect(wrapper.find('[data-test="password-form"] [role="status"]').exists()).toBe(false)
    })

    it('shows a refused new password next to its field', async () => {
      const { wrapper } = await mountView()
      const auth = useAuthStore()
      vi.spyOn(auth, 'changePassword').mockRejectedValue(new ApiError(422, { errors: { password: ['weak'] } }))

      await fill(wrapper)

      expect(wrapper.get('#profile-new-password-error').text()).toContain('щонайменше 8')
    })

    it('shows other failures as a notice', async () => {
      const { wrapper } = await mountView()
      const auth = useAuthStore()
      vi.spyOn(auth, 'changePassword').mockRejectedValue(new ApiError(500, null))

      await fill(wrapper)

      expect(wrapper.find('[data-test="password-form"] [role="alert"]').exists()).toBe(true)
    })

    it('disables the button while the request is on its way', async () => {
      const { wrapper } = await mountView()
      const auth = useAuthStore()
      vi.spyOn(auth, 'changePassword').mockReturnValue(new Promise(() => undefined))

      await fill(wrapper)

      expect(wrapper.get('[data-test="password-form"] button[type="submit"]').attributes('disabled')).toBeDefined()
    })
  })

  describe('account deletion', () => {
    async function openDialog(wrapper: ReturnType<typeof mount>) {
      await wrapper.get('[data-test="delete-account"]').trigger('click')
      await flushPromises()
    }

    async function confirm(wrapper: ReturnType<typeof mount>, password = 'secret-password') {
      await wrapper.get('#delete-account-password').setValue(password)
      await wrapper.get('[role="dialog"] form').trigger('submit')
      await flushPromises()
    }

    it('explains what is deleted and asks for the password before anything happens', async () => {
      const { wrapper } = await mountView()
      const auth = useAuthStore()
      const remove = vi.spyOn(auth, 'deleteAccount').mockResolvedValue()

      await openDialog(wrapper)

      const text = wrapper.get('[role="dialog"]').text()
      for (const part of ['бібліотека', 'цитати', 'публікації', 'книги', 'підписники', 'неможливо скасувати']) {
        expect(text).toContain(part)
      }
      expect(remove).not.toHaveBeenCalled()
    })

    it('moves the focus into the dialog, closes on Escape and gives the focus back', async () => {
      const { wrapper } = await mountView()
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
      const { wrapper } = await mountView()
      const auth = useAuthStore()
      const remove = vi.spyOn(auth, 'deleteAccount').mockResolvedValue()
      await openDialog(wrapper)

      await wrapper.get('[role="dialog"] button[type="button"]').trigger('click')

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(remove).not.toHaveBeenCalled()
    })

    it('deletes the account and goes to the login screen with a note', async () => {
      const { wrapper, router } = await mountView()
      const auth = useAuthStore()
      const remove = vi.spyOn(auth, 'deleteAccount').mockResolvedValue()
      await openDialog(wrapper)

      await confirm(wrapper)

      expect(remove).toHaveBeenCalledWith('secret-password')
      expect(router.currentRoute.value.name).toBe('login')
      expect(router.currentRoute.value.query.deleted).toBe('1')
    })

    it('does not send an empty password', async () => {
      const { wrapper } = await mountView()
      const auth = useAuthStore()
      const remove = vi.spyOn(auth, 'deleteAccount').mockResolvedValue()
      await openDialog(wrapper)

      await confirm(wrapper, '')

      expect(remove).not.toHaveBeenCalled()
    })

    it('keeps the dialog open and names the password when it is wrong', async () => {
      const { wrapper, router } = await mountView()
      const auth = useAuthStore()
      vi.spyOn(auth, 'deleteAccount').mockRejectedValue(new ApiError(422, { errors: { password: ['wrong'] } }))
      await openDialog(wrapper)

      await confirm(wrapper, 'wrong')

      expect(wrapper.get('#delete-account-password-error').text()).toBe('Пароль невірний.')
      expect(router.currentRoute.value.name).not.toBe('login')
    })

    it('shows other failures inside the dialog', async () => {
      const { wrapper } = await mountView()
      const auth = useAuthStore()
      vi.spyOn(auth, 'deleteAccount').mockRejectedValue(new ApiError(500, null))
      await openDialog(wrapper)

      await confirm(wrapper)

      expect(wrapper.find('[role="dialog"] [role="alert"]').exists()).toBe(true)
    })

    it('disables the confirm button and ignores Escape while deleting', async () => {
      const { wrapper } = await mountView()
      const auth = useAuthStore()
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

    it('explains what is in the file and that book texts are not', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.get('#profile-data-hint').text()).toContain('Самого тексту книжок у файлі немає.')
      expect(wrapper.get('[data-test="export-data"]').text()).toBe('Завантажити мої дані')
    })

    it('sits above the danger zone', async () => {
      const { wrapper } = await mountView()

      const html = wrapper.html()
      expect(html.indexOf('profile-data-title')).toBeLessThan(html.indexOf('profile-danger-title'))
    })

    it('downloads the file', async () => {
      vi.useFakeTimers({ toFake: ['setTimeout'] })
      const { createObjectURL, revokeObjectURL, downloads } = stubDownload()
      const file = new Blob(['{}'])
      vi.mocked(authApi.exportData).mockResolvedValue(file)
      const { wrapper } = await mountView()

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
      vi.mocked(authApi.exportData).mockReturnValue(new Promise((resolve) => (finish = resolve)))
      const { wrapper } = await mountView()

      await wrapper.get('[data-test="export-data"]').trigger('click')

      expect(wrapper.get('[data-test="export-data"]').attributes('disabled')).toBeDefined()
      expect(wrapper.get('[data-test="export-data"]').text()).toBe('Готуємо файл…')

      finish(new Blob([]))
      await flushPromises()

      expect(wrapper.get('[data-test="export-data"]').attributes('disabled')).toBeUndefined()
    })

    it('shows an error notice when the download fails', async () => {
      const { downloads } = stubDownload()
      vi.mocked(authApi.exportData).mockRejectedValue(new ApiError(500, null))
      const { wrapper } = await mountView()

      await wrapper.get('[data-test="export-data"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(true)
      expect(downloads).toEqual([])
      expect(wrapper.get('[data-test="export-data"]').attributes('disabled')).toBeUndefined()
    })

    it('says the limit is per hour when it is reached', async () => {
      stubDownload()
      vi.mocked(authApi.exportData).mockRejectedValue(new ApiError(429, null))
      const { wrapper } = await mountView()

      await wrapper.get('[data-test="export-data"]').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('тричі на годину')
    })
  })
})
