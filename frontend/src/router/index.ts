import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/components/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'
import AddBookView from '@/views/AddBookView.vue'
import BookView from '@/views/BookView.vue'
import HomeView from '@/views/HomeView.vue'
import LibraryView from '@/views/LibraryView.vue'
import LoginView from '@/views/LoginView.vue'
import ProfileView from '@/views/ProfileView.vue'
import RegisterView from '@/views/RegisterView.vue'

export const router = createRouter({
  history: createWebHistory(),
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
    return { name: 'login' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'home' }
  }
})
