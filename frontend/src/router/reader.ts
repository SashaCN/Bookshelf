import type { RouteRecordRaw } from 'vue-router'

/** Full-screen screens of the reader, outside of the layout with the tab bar. `:id` is the id of the library entry. */
export const readerRoutes: RouteRecordRaw[] = [
  {
    path: '/read/:id(\\d+)',
    // Not "reader": that is the name of the profile of a reader of the community.
    name: 'read',
    component: () => import('@/views/ReaderView.vue'),
    props: (route) => ({ id: Number(route.params.id) }),
    meta: { requiresAuth: true, title: 'reader.title' },
  },
]
