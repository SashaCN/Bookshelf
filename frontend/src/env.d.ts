/// <reference types="vite/client" />

/** The version from package.json, injected by Vite (`define` in vite.config.ts), also under Vitest. */
declare global {
  const __APP_VERSION__: string
}

import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    /** Redirect to the login page when the visitor is not signed in. */
    requiresAuth?: boolean
    /** Redirect to the home page when the visitor is already signed in. */
    guestOnly?: boolean
    /** The bottom tab that stays highlighted on this page. */
    tab?: 'home' | 'library' | 'quotes' | 'stats' | 'community'
    /** The i18n key of the page title shown in the browser tab and the task switcher. */
    title?: string
  }
}
