import type { LoginPayload, RegisterPayload, User } from '@/types/api'
import { http } from './http'

export const authApi = {
  me: () => http.get<{ data: User }>('/api/me').then((response) => response.data),
  login: (payload: LoginPayload) => http.post<unknown>('/api/auth/login', payload),
  register: (payload: RegisterPayload) => http.post<unknown>('/api/auth/register', payload),
  logout: () => http.post<unknown>('/api/auth/logout'),
}
