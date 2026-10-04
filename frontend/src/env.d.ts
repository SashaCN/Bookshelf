/// <reference types="vite/client" />

import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    /** Redirect to the login page when the visitor is not signed in. */
    requiresAuth?: boolean
    /** Redirect to the home page when the visitor is already signed in. */
    guestOnly?: boolean
    /** The bottom tab that stays highlighted on this page. */
    tab?: 'home' | 'library'
  }
}
