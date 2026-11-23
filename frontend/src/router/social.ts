import type { RouteRecordRaw } from 'vue-router'

// Routes of the community. They live inside the layout with the tab bar, under the "Спільнота" tab.
export const socialRoutes: RouteRecordRaw[] = [
  {
    path: 'community',
    name: 'community',
    component: () => import('@/views/CommunityView.vue'),
    meta: { tab: 'community', title: 'social.title' },
  },
  {
    path: 'community/new',
    name: 'community-new',
    component: () => import('@/views/ComposePostView.vue'),
    meta: { tab: 'community', title: 'social.compose.title' },
  },
  {
    path: 'community/blocked',
    name: 'community-blocked',
    component: () => import('@/views/BlockedReadersView.vue'),
    meta: { tab: 'community', title: 'social.blocked.title' },
  },
  {
    path: 'community/posts/:id(\\d+)',
    name: 'community-post',
    component: () => import('@/views/PostView.vue'),
    props: (route) => ({ id: Number(route.params.id) }),
    meta: { tab: 'community', title: 'social.post.title' },
  },
  {
    // Handles are lower case Latin letters, digits and underscores.
    path: 'u/:handle([a-zA-Z0-9_]+)',
    name: 'reader',
    component: () => import('@/views/ReaderProfileView.vue'),
    props: (route) => ({ handle: String(route.params.handle).toLowerCase() }),
    meta: { tab: 'community', title: 'social.profile.title' },
  },
]
