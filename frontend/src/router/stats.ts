import type { RouteRecordRaw } from 'vue-router'

// Routes of the statistics beyond the main screen. They live inside the layout with the tab bar, under the "Статистика" tab.
export const statsRoutes: RouteRecordRaw[] = [
  {
    path: 'stats/year/:year(\\d{4})',
    name: 'year-recap',
    component: () => import('@/views/YearRecapView.vue'),
    props: (route) => ({ year: Number(route.params.year) }),
    meta: { tab: 'stats', title: 'recap.pageTitle' },
  },
]
