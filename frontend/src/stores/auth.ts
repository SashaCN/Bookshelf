import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { clearApiCache } from '@/pwa/clearApiCache'
import type { ChangePasswordPayload, LoginPayload, RegisterPayload, UpdateProfilePayload, User } from '@/types/api'
import { useLibraryStore } from './library'
import { useQuotesStore } from './quotes'
import { useReaderStore } from './reader'
import { useSocialStore } from './social'

function resetReaderData(): void {
  useLibraryStore().reset()
  useQuotesStore().reset()
  useSocialStore().reset()
  useReaderStore().reset()
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
      // The cookie ran out while the app was closed: the answers cached for that reader must not outlive it.
      clearApiCache()
    }

    initialized.value = true
  }

  async function login(payload: LoginPayload): Promise<void> {
    await authApi.login(payload)
    // Whoever used this device before must not leave their books behind.
    resetReaderData()
    clearApiCache()
    user.value = await authApi.me()
    initialized.value = true
  }

  async function register(payload: RegisterPayload): Promise<void> {
    await authApi.register(payload)
    resetReaderData()
    clearApiCache()
    user.value = await authApi.me()
    initialized.value = true
  }

  async function updateProfile(payload: UpdateProfilePayload): Promise<void> {
    user.value = await authApi.updateMe(payload)
  }

  async function changePassword(payload: ChangePasswordPayload): Promise<void> {
    await authApi.changePassword(payload)
  }

  /** Deletes the account for good; the server ends the session, so everything of the reader is forgotten here. */
  async function deleteAccount(password: string): Promise<void> {
    await authApi.deleteMe(password)
    expireSession()
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

  return { user, initialized, isAuthenticated, init, login, register, updateProfile, changePassword, deleteAccount, markFollowersSeen, logout, expireSession }
})
