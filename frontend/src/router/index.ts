import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/components/AppLayout.vue'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import HomeView from '@/views/HomeView.vue'
import LoginView from '@/views/LoginView.vue'
import { redirectFor } from './access'
import { accountRoutes } from './account'
import { libraryImportRoutes } from './libraryImport'
import { readerRoutes } from './reader'
import { socialRoutes } from './social'
import { statsRoutes } from './stats'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'home', component: HomeView, meta: { tab: 'home', title: 'tabs.reading' } },
        { path: 'library', name: 'library', component: () => import('@/views/LibraryView.vue'), meta: { tab: 'library', title: 'library.title' } },
        { path: 'library/add', name: 'library-add', component: () => import('@/views/AddBookView.vue'), meta: { tab: 'library', title: 'add.title' } },
        {
          path: 'library/:id(\\d+)',
          name: 'book',
          component: () => import('@/views/BookView.vue'),
          props: (route) => ({ id: Number(route.params.id) }),
          meta: { tab: 'library', title: 'book.title' },
        },
        { path: 'quotes', name: 'quotes', component: () => import('@/views/QuotesView.vue'), meta: { tab: 'quotes', title: 'quotes.title' } },
        {
          path: 'quotes/new',
          name: 'quote-new',
          component: () => import('@/views/QuoteFormView.vue'),
          meta: { tab: 'quotes', title: 'quotes.form.createTitle' },
        },
        {
          path: 'quotes/:id(\\d+)/edit',
          name: 'quote-edit',
          component: () => import('@/views/QuoteFormView.vue'),
          props: (route) => ({ id: Number(route.params.id) }),
          meta: { tab: 'quotes', title: 'quotes.form.editTitle' },
        },
        {
          path: 'quotes/:id(\\d+)/share',
          name: 'quote-share',
          component: () => import('@/views/QuoteShareView.vue'),
          props: (route) => ({ id: Number(route.params.id) }),
          meta: { tab: 'quotes', title: 'quotes.shareCard.title' },
        },
        { path: 'stats', name: 'stats', component: () => import('@/views/StatsView.vue'), meta: { tab: 'stats', title: 'stats.title' } },
        { path: 'profile', name: 'profile', component: () => import('@/views/ProfileView.vue'), meta: { title: 'profile.title' } },
        ...statsRoutes,
        ...libraryImportRoutes,
        ...socialRoutes,
        // Last: anything that matched nothing above. Inside the layout, so the reader keeps the tab bar.
        { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue'), meta: { title: 'notFound.title' } },
      ],
    },
    // Full-screen screens, outside of the layout with the tab bar.
    ...readerRoutes,
    { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true, title: 'auth.login.title' } },
    { path: '/register', name: 'register', component: () => import('@/views/RegisterView.vue'), meta: { guestOnly: true, title: 'auth.register.title' } },
    ...accountRoutes,
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  try {
    await auth.init()
  } catch {
    // The API answered with something unexpected: treat the visitor as a guest, the login form will show the error.
  }

  return redirectFor(to, auth) ?? undefined
})

// The title names the screen in the task switcher, in the history and for screen readers.
router.afterEach((to) => {
  const title = to.meta.title ? i18n.global.t(to.meta.title) : null
  document.title = title ? `${title} · Bookshelf` : 'Bookshelf'
})
