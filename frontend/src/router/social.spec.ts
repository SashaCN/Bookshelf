import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { i18n } from '@/i18n'
import { socialRoutes } from './social'

// The routes are children of the layout in the app; here they stand on their own.
function createSocialRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      ...socialRoutes.map((route) => ({ ...route, path: `/${route.path}` })),
      { path: '/:pathMatch(.*)*', name: 'nothing', component: { template: '<div />' } },
    ],
  })
}

describe('the routes of the community', () => {
  it('are all under the community tab, with a title', () => {
    for (const route of socialRoutes) {
      expect(route.meta?.tab).toBe('community')
      expect(i18n.global.te(route.meta?.title as string)).toBe(true)
    }
  })

  it.each([
    ['/community', 'community'],
    ['/community/new', 'community-new'],
    ['/community/blocked', 'community-blocked'],
    ['/community/posts/12', 'community-post'],
    ['/u/olena_k', 'reader'],
  ])('answer %s with "%s"', async (path, name) => {
    const router = createSocialRouter()

    await router.push(path)

    expect(router.currentRoute.value.name).toBe(name)
  })

  it('hand the id of a post to the page as a number', async () => {
    const router = createSocialRouter()
    await router.push('/community/posts/12')

    const route = router.currentRoute.value
    const props = (route.matched[0]!.props.default as (route: unknown) => unknown)(route)

    expect(props).toEqual({ id: 12 })
  })

  it('do not take anything but a number for a post', async () => {
    const router = createSocialRouter()

    await router.push('/community/posts/abc')

    expect(router.currentRoute.value.name).toBe('nothing')
  })

  it('hand the handle to the profile page in lower case', async () => {
    const router = createSocialRouter()
    await router.push('/u/Olena_K')

    const route = router.currentRoute.value
    const props = (route.matched[0]!.props.default as (route: unknown) => unknown)(route)

    expect(props).toEqual({ handle: 'olena_k' })
  })

  it('do not take a handle with characters a handle cannot have', async () => {
    const router = createSocialRouter()

    await router.push('/u/olena-k')

    expect(router.currentRoute.value.name).toBe('nothing')
  })
})
