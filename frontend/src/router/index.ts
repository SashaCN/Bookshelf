import { createRouter, createWebHashHistory, createWebHistory } from 'vue-router'
import AppLayout from '@/components/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { safeRedirect } from './redirect'
import HomeView from '@/views/HomeView.vue'
import LoginView from '@/views/LoginView.vue'

export const router = createRouter({
  // The standalone demo is opened from a file, where there is no server to answer deep links.
  history: import.meta.env.VITE_DEMO ? createWebHashHistory() : createWebHistory(),
  routes: [
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'home', component: HomeView, meta: { tab: 'home' } },
        { path: 'library', name: 'library', component: () => import('@/views/LibraryView.vue'), meta: { tab: 'library' } },
        { path: 'library/add', name: 'library-add', component: () => import('@/views/AddBookView.vue'), meta: { tab: 'library' } },
        {
          path: 'library/import',
          name: 'library-import',
          component: () => import('@/views/LibraryImportView.vue'),
          meta: { tab: 'library' },
        },
        {
          path: 'library/:id(\\d+)',
          name: 'book',
          component: () => import('@/views/BookView.vue'),
          props: (route) => ({ id: Number(route.params.id) }),
          meta: { tab: 'library' },
        },
        { path: 'quotes', name: 'quotes', component: () => import('@/views/QuotesView.vue'), meta: { tab: 'quotes' } },
        { path: 'quotes/new', name: 'quote-new', component: () => import('@/views/QuoteFormView.vue'), meta: { tab: 'quotes' } },
        {
          path: 'quotes/:id(\\d+)/edit',
          name: 'quote-edit',
          component: () => import('@/views/QuoteFormView.vue'),
          props: (route) => ({ id: Number(route.params.id) }),
          meta: { tab: 'quotes' },
        },
        {
          path: 'quotes/:id(\\d+)/share',
          name: 'quote-share',
          component: () => import('@/views/QuoteShareView.vue'),
          props: (route) => ({ id: Number(route.params.id) }),
          meta: { tab: 'quotes' },
        },
        { path: 'feed', name: 'feed', component: () => import('@/views/FeedView.vue') },
        { path: 'people', name: 'people-search', component: () => import('@/views/PeopleSearchView.vue') },
        {
          path: 'u/:username',
          name: 'public-profile',
          component: () => import('@/views/PublicProfileView.vue'),
          props: (route) => ({ username: String(route.params.username) }),
          // Shared profile links work for guests too; signed-in readers get the usual layout.
          meta: { requiresAuth: false },
        },
        {
          path: 'u/:username/followers',
          name: 'followers',
          component: () => import('@/views/PeopleListView.vue'),
          props: (route) => ({ username: String(route.params.username), kind: 'followers' }),
        },
        {
          path: 'u/:username/following',
          name: 'following',
          component: () => import('@/views/PeopleListView.vue'),
          props: (route) => ({ username: String(route.params.username), kind: 'following' }),
        },
        { path: 'blocks', name: 'blocks', component: () => import('@/views/BlockedUsersView.vue') },
        { path: 'reader', name: 'reader', component: () => import('@/views/ReaderListView.vue') },
        {
          path: 'reader/:id(\\d+)',
          name: 'reader-view',
          component: () => import('@/views/ReaderView.vue'),
          props: (route) => ({ id: Number(route.params.id) }),
        },
        { path: 'stats', name: 'stats', component: () => import('@/views/StatsView.vue'), meta: { tab: 'stats' } },
        {
          path: 'stats/year/:year(\\d{4})',
          name: 'year-recap',
          component: () => import('@/views/YearRecapView.vue'),
          props: (route) => ({ year: Number(route.params.year) }),
          meta: { tab: 'stats' },
        },
        { path: 'profile', name: 'profile', component: () => import('@/views/ProfileView.vue') },
      ],
    },
    { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
    { path: '/register', name: 'register', component: () => import('@/views/RegisterView.vue'), meta: { guestOnly: true } },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('@/views/ForgotPasswordView.vue'), meta: { guestOnly: true } },
    {
      path: '/reset-password/:token',
      name: 'reset-password',
      component: () => import('@/views/ResetPasswordView.vue'),
      props: (route) => ({ token: String(route.params.token) }),
      meta: { guestOnly: true },
    },
    { path: '/:pathMatch(.*)*', redirect: { name: 'home' } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  try {
    await auth.init()
  } catch {
    // The API is unreachable: treat the visitor as a guest, the login form will show the error.
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    // Remember where the visitor was going, so signing in takes them there instead of to the home page.
    return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return safeRedirect(to.query.redirect) ?? { name: 'home' }
  }
})
