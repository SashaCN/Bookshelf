import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router'
import { safeRedirect } from './redirect'

interface Visitor {
  isAuthenticated: boolean
  /** The server could not be reached, so it is not known who the visitor is. */
  unreachable: boolean
}

/**
 * Where a visitor has to go instead of the page they asked for, or null when they may stay.
 * Signed-in readers do not see the sign-in screens, guests do not see the app; and while the server cannot be
 * reached nobody is sent anywhere, because "not signed in" would be a guess.
 */
export function redirectFor(to: RouteLocationNormalized, visitor: Visitor): RouteLocationRaw | null {
  if (visitor.unreachable) {
    return null
  }

  if (to.meta.requiresAuth && !visitor.isAuthenticated) {
    // Remember where the visitor was going, so signing in takes them there instead of to the home screen.
    return { name: 'login', query: to.fullPath && to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
  }

  if (to.meta.guestOnly && visitor.isAuthenticated) {
    return safeRedirect(to.query?.redirect) ?? { name: 'home' }
  }

  return null
}
