import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { clearApiCache } from '@/pwa/clearApiCache'
import type { LoginPayload, RegisterPayload, UpdateProfilePayload, User } from '@/types/api'
import { useLibraryStore } from './library'
import { useQuotesStore } from './quotes'
import { useSocialStore } from './social'

function resetReaderData(): void {
  useLibraryStore().reset()
  useQuotesStore().reset()
  useSocialStore().reset()
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  /** Restores the session from the cookie; a 401 simply means the visitor is a guest. */
  async function init(): Promise<void> {
    if (initialized.value) {
      return
    }

    try {
      user.value = await authApi.me()
    } catch (error) {
      if (!(error instanceof ApiError && error.status === 401)) {
        throw error
      }
      user.value = null
    }

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

  /** Tells the server the followers list was opened, and drops the badge without asking for the user again. */
  async function markFollowersSeen(): Promise<void> {
    if (!user.value || user.value.new_followers_count === 0) {
      return
    }

    await authApi.followersSeen()
    if (user.value) {
      user.value = { ...user.value, new_followers_count: 0 }
    }
  }

  async function logout(): Promise<void> {
    await authApi.logout()
    user.value = null
    resetReaderData()
    clearApiCache()
  }

  /** The server no longer knows this session: forget the reader without asking it again. */
  function expireSession(): void {
    user.value = null
    resetReaderData()
    clearApiCache()
  }

  return { user, initialized, isAuthenticated, init, login, register, updateProfile, markFollowersSeen, logout, expireSession }
})
