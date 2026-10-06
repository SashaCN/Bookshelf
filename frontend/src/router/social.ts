import type { RouteRecordRaw } from 'vue-router'
import BlockedReadersView from '@/views/BlockedReadersView.vue'
import CommunityView from '@/views/CommunityView.vue'
import ComposePostView from '@/views/ComposePostView.vue'
import PostView from '@/views/PostView.vue'
import ReaderProfileView from '@/views/ReaderProfileView.vue'

// Routes of the community. They live inside the layout with the tab bar, under the "Спільнота" tab.
export const socialRoutes: RouteRecordRaw[] = [
  {
    path: 'community',
    name: 'community',
    component: CommunityView,
    meta: { tab: 'community', title: 'social.title' },
  },
  {
    path: 'community/new',
    name: 'community-new',
    component: ComposePostView,
    meta: { tab: 'community', title: 'social.compose.title' },
  },
  {
    path: 'community/blocked',
    name: 'community-blocked',
    component: BlockedReadersView,
    meta: { tab: 'community', title: 'social.blocked.title' },
  },
  {
    path: 'community/posts/:id(\\d+)',
    name: 'community-post',
    component: PostView,
    props: (route) => ({ id: Number(route.params.id) }),
    meta: { tab: 'community', title: 'social.post.title' },
  },
  {
    // Handles are lower case Latin letters, digits and underscores.
    path: 'u/:handle([a-zA-Z0-9_]+)',
    name: 'reader',
    component: ReaderProfileView,
    props: (route) => ({ handle: String(route.params.handle).toLowerCase() }),
    meta: { tab: 'community', title: 'social.profile.title' },
  },
]
