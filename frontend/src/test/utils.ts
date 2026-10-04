import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { i18n } from '@/i18n'

const stub = { template: '<div />' }

/** A router with the app's route names but stub components, for testing one view at a time. */
export function createTestRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: stub, meta: { tab: 'home' } },
      { path: '/library', name: 'library', component: stub, meta: { tab: 'library' } },
      { path: '/library/add', name: 'library-add', component: stub, meta: { tab: 'library' } },
      { path: '/library/:id', name: 'book', component: stub, meta: { tab: 'library' } },
      { path: '/profile', name: 'profile', component: stub },
      { path: '/login', name: 'login', component: stub },
    ],
  })
}

/** Pinia, router and i18n wired up the way the real app does it. */
export function createTestEnvironment() {
  const pinia = createPinia()
  setActivePinia(pinia)
  const router = createTestRouter()

  return { pinia, router, plugins: [pinia, router, i18n] }
}
