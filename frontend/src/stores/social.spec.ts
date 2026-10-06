import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/api/auth'
import { socialApi } from '@/api/social'
import { makeComment, makePost, makeProfile, makeReader } from '@/test/socialFixtures'
import { useAuthStore } from './auth'
import { useBlocksStore } from './blocks'
import { useCommentsStore } from './comments'
import { useFeedStore } from './feed'
import { usePostsStore } from './posts'
import { useProfilesStore } from './profiles'
import { resetSocialData } from './social'

vi.mock('@/api/auth', () => ({
  authApi: { me: vi.fn(), login: vi.fn(), register: vi.fn(), logout: vi.fn() },
}))
vi.mock('@/api/social', () => ({
  socialApi: { feed: vi.fn(), profile: vi.fn(), comments: vi.fn(), blocks: vi.fn() },
}))

async function fillEverything() {
  vi.mocked(socialApi.feed).mockResolvedValue({ data: [makePost({ id: 1 })], links: { next: null } })
  vi.mocked(socialApi.profile).mockResolvedValue(makeProfile())
  vi.mocked(socialApi.comments).mockResolvedValue({ data: [makeComment()], links: { next: null } })
  vi.mocked(socialApi.blocks).mockResolvedValue({ data: [makeReader()], links: { next: null } })

  await useFeedStore().load('all')
  await useProfilesStore().load('olena_k')
  await useCommentsStore().load(1)
  await useBlocksStore().load()
}

function expectEverythingForgotten() {
  expect(usePostsStore().get(1)).toBeUndefined()
  expect(useFeedStore().items).toEqual([])
  expect(useFeedStore().loaded).toBe(false)
  expect(useProfilesStore().profile).toBeNull()
  expect(useCommentsStore().items).toEqual([])
  expect(useCommentsStore().loaded).toBe(false)
  expect(useBlocksStore().items).toEqual([])
  expect(useBlocksStore().loaded).toBe(false)
}

describe('resetting the community', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('forgets the posts, the feed, the profile, the comments and the blocked readers', async () => {
    await fillEverything()
    expect(usePostsStore().get(1)).toBeDefined()

    resetSocialData()

    expectEverythingForgotten()
  })

  describe('when somebody else signs in on the same device', () => {
    const user = { id: 2, name: 'Taras', email: 't@example.com', handle: 'taras', bio: null, timezone: 'Europe/Kyiv' }

    it('forgets it on login', async () => {
      await fillEverything()
      vi.mocked(authApi.login).mockResolvedValue({})
      vi.mocked(authApi.me).mockResolvedValue(user)

      await useAuthStore().login({ email: 't@example.com', password: 'secret-password' })

      expectEverythingForgotten()
    })

    it('forgets it on registration', async () => {
      await fillEverything()
      vi.mocked(authApi.register).mockResolvedValue({})
      vi.mocked(authApi.me).mockResolvedValue(user)

      await useAuthStore().register({ name: 'Taras', email: 't@example.com', password: 'secret-password', timezone: 'Europe/Kyiv' })

      expectEverythingForgotten()
    })

    it('forgets it on logout', async () => {
      await fillEverything()
      vi.mocked(authApi.logout).mockResolvedValue({})

      await useAuthStore().logout()

      expectEverythingForgotten()
    })

    it('forgets it when the session expires', async () => {
      await fillEverything()

      useAuthStore().expireSession()

      expectEverythingForgotten()
    })
  })
})
