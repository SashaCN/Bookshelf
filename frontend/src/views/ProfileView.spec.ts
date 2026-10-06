import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { createTestEnvironment } from '@/test/utils'
import type { User } from '@/types/api'
import ProfileView from './ProfileView.vue'

vi.mock('@/api/auth', () => ({
  authApi: { me: vi.fn(), updateMe: vi.fn(), login: vi.fn(), register: vi.fn(), logout: vi.fn() },
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
})
