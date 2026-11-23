import type { RouteRecordRaw } from 'vue-router'

// Importing a library from another service. Inside the layout with the tab bar, under the "Бібліотека" tab.
export const libraryImportRoutes: RouteRecordRaw[] = [
  {
    path: 'library/import',
    name: 'library-import',
    component: () => import('@/views/LibraryImportView.vue'),
    meta: { tab: 'library', title: 'libraryImport.title' },
  },
]
