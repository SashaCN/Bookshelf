import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api/auth'
import { ApiError, NetworkError } from '@/api/http'
import type { LoginPayload, RegisterPayload, UpdateProfilePayload, User } from '@/types/api'
import { useLibraryStore } from './library'
import { useQuotesStore } from './quotes'
import { resetSocialData } from './social'

function resetReaderData(): void {
  useLibraryStore().reset()
  useQuotesStore().reset()
  resetSocialData()
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const initialized = ref(false)
  /** The server could not be reached when the app started, so it is unknown whether the reader is signed in. */
  const unreachable = ref(false)
  /** The first question to the server (who is signed in?) is on its way. */
  const initializing = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  /** Restores the session from the cookie; a 401 simply means the visitor is a guest. */
  async function init(): Promise<void> {
    if (initialized.value) {
      return
    }

    initializing.value = true

    try {
      user.value = await authApi.me()
    } catch (error) {
      // No answer, or the server in front of the app answering for a dead app (502, 503, 504): the reader is not
      // a guest, the server is just out of reach, and showing the sign-in form would be wrong. It can be tried again.
      if (error instanceof NetworkError || (error instanceof ApiError && error.status >= 500)) {
        unreachable.value = true
        return
      }

      if (!(error instanceof ApiError && error.status === 401)) {
        throw error
      }
      user.value = null
    } finally {
      initializing.value = false
    }

    unreachable.value = false
    initialized.value = true
  }

  async function login(payload: LoginPayload): Promise<void> {
    await authApi.login(payload)
    // Whoever used this device before must not leave their books behind.
    resetReaderData()
    user.value = await authApi.me()
    initialized.value = true
  }

  async function register(payload: RegisterPayload): Promise<void> {
    await authApi.register(payload)
    resetReaderData()
    user.value = await authApi.me()
    initialized.value = true
  }

  async function updateProfile(payload: UpdateProfilePayload): Promise<void> {
    user.value = await authApi.updateMe(payload)
  }

  /** The server no longer knows this session (it expired): forget the reader, but keep the page they are on. */
  function expireSession(): void {
    user.value = null
    resetReaderData()
  }

  async function logout(): Promise<void> {
    await authApi.logout()
    user.value = null
    resetReaderData()
  }

  return { user, initialized, unreachable, initializing, isAuthenticated, init, login, register, updateProfile, expireSession, logout }
})
