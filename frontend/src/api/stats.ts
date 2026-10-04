import type { DailyStats, GoalProgress, StatsSummary } from '@/types/api'
import { http } from './http'

export const statsApi = {
  summary: () => http.get<{ data: StatsSummary }>('/api/stats/summary').then((response) => response.data),
  /** Pages per day for the last `days` days, counted up to today in the reader's own calendar. */
  daily: (days: number) => http.get<DailyStats>(`/api/stats/daily?days=${days}`),
  goal: (year: number) => http.get<{ data: GoalProgress }>(`/api/goals/${year}`).then((response) => response.data),
  setGoal: (year: number, targetBooks: number) =>
    http.put<{ data: GoalProgress }>(`/api/goals/${year}`, { target_books: targetBooks }).then((response) => response.data),
  removeGoal: (year: number) => http.delete<null>(`/api/goals/${year}`),
}
