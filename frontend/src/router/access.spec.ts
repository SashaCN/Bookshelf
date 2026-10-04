import type { RouteLocationNormalized } from 'vue-router'
import { describe, expect, it } from 'vitest'
import { redirectFor } from './access'

function route(meta: Record<string, boolean>): RouteLocationNormalized {
  return { meta } as unknown as RouteLocationNormalized
}

const guest = { isAuthenticated: false, unreachable: false }
const reader = { isAuthenticated: true, unreachable: false }

describe('redirectFor', () => {
  it('sends a guest from the app to the sign-in form', () => {
    expect(redirectFor(route({ requiresAuth: true }), guest)).toEqual({ name: 'login' })
  })

  it('lets a signed-in reader into the app', () => {
    expect(redirectFor(route({ requiresAuth: true }), reader)).toBeNull()
  })

  it('sends a signed-in reader from the sign-in screens to the home screen', () => {
    expect(redirectFor(route({ guestOnly: true }), reader)).toEqual({ name: 'home' })
  })

  it('lets a guest see the sign-in screens', () => {
    expect(redirectFor(route({ guestOnly: true }), guest)).toBeNull()
  })

  it('does not restrict a screen that is for everybody', () => {
    expect(redirectFor(route({}), guest)).toBeNull()
    expect(redirectFor(route({}), reader)).toBeNull()
  })

  it('sends nobody anywhere while the server cannot be reached, because who they are is unknown', () => {
    const unknown = { isAuthenticated: false, unreachable: true }

    expect(redirectFor(route({ requiresAuth: true }), unknown)).toBeNull()
    expect(redirectFor(route({ guestOnly: true }), unknown)).toBeNull()
  })
})
