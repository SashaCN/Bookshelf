import type { ChangePasswordPayload, LoginPayload, RegisterPayload, ResetPasswordPayload, UpdateProfilePayload, User } from '@/types/api'
import { http } from './http'

export const authApi = {
  me: () => http.get<{ data: User }>('/api/me').then((response) => response.data),
  updateMe: (payload: UpdateProfilePayload) =>
    http.patch<{ data: User }>('/api/me', payload).then((response) => response.data),
  deleteMe: (password: string) => http.delete<null>('/api/me', { password }),
  changePassword: (payload: ChangePasswordPayload) => http.put<unknown>('/api/auth/user/password', payload),
  exportData: () => http.download('/api/me/export'),
  followersSeen: () => http.post<null>('/api/me/followers-seen'),
  login: (payload: LoginPayload) => http.post<unknown>('/api/auth/login', payload),
  register: (payload: RegisterPayload) => http.post<unknown>('/api/auth/register', payload),
  forgotPassword: (email: string) => http.post<unknown>('/api/auth/forgot-password', { email }),
  resetPassword: (payload: ResetPasswordPayload) => http.post<unknown>('/api/auth/reset-password', payload),
  logout: () => http.post<unknown>('/api/auth/logout'),
}
