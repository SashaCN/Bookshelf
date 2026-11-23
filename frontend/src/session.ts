import type { Router } from 'vue-router'
import { onUnauthorized } from '@/api/http'
import { useAuthStore } from '@/stores/auth'

/**
 * A 401 for a signed-in reader means the session ran out while the app was open. Without this, every screen would keep
 * showing its own error and the reader would not know that signing in again is the fix.
 */
export function installSessionExpiry(router: Router): void {
  onUnauthorized(() => {
    const auth = useAuthStore()
    if (!auth.isAuthenticated) return

    auth.expireSession()
    const { fullPath } = router.currentRoute.value
    void router.replace({ name: 'login', query: fullPath === '/' ? {} : { redirect: fullPath } })
  })
}
