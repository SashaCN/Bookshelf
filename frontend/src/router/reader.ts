import type { RouteRecordRaw } from 'vue-router'
import ReaderView from '@/views/ReaderView.vue'

/** Full-screen screens of the reader, outside of the layout with the tab bar. `:id` is the id of the library entry. */
export const readerRoutes: RouteRecordRaw[] = [
  {
    path: '/read/:id(\\d+)',
    // Not "reader": that is the name of the profile of a reader of the community.
    name: 'read',
    component: ReaderView,
    props: (route) => ({ id: Number(route.params.id) }),
    meta: { requiresAuth: true, title: 'reader.title' },
  },
]
