import type { RouteRecordRaw } from 'vue-router'

/** Screens for a guest who cannot sign in: asking for a reset link, and choosing a new password with it. */
export const accountRoutes: RouteRecordRaw[] = [
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/ForgotPasswordView.vue'),
    meta: { guestOnly: true, title: 'account.forgot.title' },
  },
  {
    path: '/reset-password/:token',
    name: 'reset-password',
    component: () => import('@/views/ResetPasswordView.vue'),
    props: (route) => ({ token: String(route.params.token) }),
    meta: { guestOnly: true, title: 'account.reset.title' },
  },
]
