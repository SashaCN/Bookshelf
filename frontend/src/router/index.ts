import { createRouter, createWebHashHistory, createWebHistory } from 'vue-router'
import AppLayout from '@/components/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { safeRedirect } from './redirect'
import AddBookView from '@/views/AddBookView.vue'
import BookView from '@/views/BookView.vue'
import FeedView from '@/views/FeedView.vue'
import HomeView from '@/views/HomeView.vue'
import LibraryView from '@/views/LibraryView.vue'
import LoginView from '@/views/LoginView.vue'
import ProfileView from '@/views/ProfileView.vue'
import PeopleSearchView from '@/views/PeopleSearchView.vue'
import PeopleListView from '@/views/PeopleListView.vue'
import PublicProfileView from '@/views/PublicProfileView.vue'
import QuoteFormView from '@/views/QuoteFormView.vue'
import QuoteShareView from '@/views/QuoteShareView.vue'
import QuotesView from '@/views/QuotesView.vue'
import ReaderListView from '@/views/ReaderListView.vue'
import ReaderView from '@/views/ReaderView.vue'
import RegisterView from '@/views/RegisterView.vue'
import StatsView from '@/views/StatsView.vue'

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
        { path: 'library', name: 'library', component: LibraryView, meta: { tab: 'library' } },
        { path: 'library/add', name: 'library-add', component: AddBookView, meta: { tab: 'library' } },
        {
          path: 'library/:id(\\d+)',
          name: 'book',
          component: BookView,
          props: (route) => ({ id: Number(route.params.id) }),
          meta: { tab: 'library' },
        },
        { path: 'quotes', name: 'quotes', component: QuotesView, meta: { tab: 'quotes' } },
        { path: 'quotes/new', name: 'quote-new', component: QuoteFormView, meta: { tab: 'quotes' } },
        {
          path: 'quotes/:id(\\d+)/edit',
          name: 'quote-edit',
          component: QuoteFormView,
          props: (route) => ({ id: Number(route.params.id) }),
          meta: { tab: 'quotes' },
        },
        {
          path: 'quotes/:id(\\d+)/share',
          name: 'quote-share',
          component: QuoteShareView,
          props: (route) => ({ id: Number(route.params.id) }),
          meta: { tab: 'quotes' },
        },
        { path: 'feed', name: 'feed', component: FeedView },
        { path: 'people', name: 'people-search', component: PeopleSearchView },
        {
          path: 'u/:username',
          name: 'public-profile',
          component: PublicProfileView,
          props: (route) => ({ username: String(route.params.username) }),
        },
        {
          path: 'u/:username/followers',
          name: 'followers',
          component: PeopleListView,
          props: (route) => ({ username: String(route.params.username), kind: 'followers' }),
        },
        {
          path: 'u/:username/following',
          name: 'following',
          component: PeopleListView,
          props: (route) => ({ username: String(route.params.username), kind: 'following' }),
        },
        { path: 'reader', name: 'reader', component: ReaderListView },
        {
          path: 'reader/:id(\\d+)',
          name: 'reader-view',
          component: ReaderView,
          props: (route) => ({ id: Number(route.params.id) }),
        },
        { path: 'stats', name: 'stats', component: StatsView, meta: { tab: 'stats' } },
        { path: 'profile', name: 'profile', component: ProfileView },
      ],
    },
    { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
    { path: '/register', name: 'register', component: RegisterView, meta: { guestOnly: true } },
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
