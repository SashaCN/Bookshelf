<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { redirectFor } from '@/router/access'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const trying = ref(false)

/** Asks the server again who is signed in; if it answers, the app goes on where it was (or to the sign-in form). */
async function retry() {
  trying.value = true

  try {
    await auth.init()

    if (!auth.unreachable) {
      const target = redirectFor(router.currentRoute.value, auth)
      if (target) await router.replace(target)
    }
  } finally {
    trying.value = false
  }
}
</script>

<template>
  <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col items-center justify-center gap-3 px-6 text-center">
    <svg viewBox="0 0 24 24" class="size-12 text-ink-muted" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M2 8.8a15 15 0 0 1 4.2-2.4M22 8.8a15 15 0 0 0-8.3-3.6M5.5 12.4a10 10 0 0 1 2.7-1.7M18.5 12.4a10 10 0 0 0-3.1-2M9 16a5 5 0 0 1 6 0M12 20h.01M3 3l18 18" />
    </svg>
    <h1 class="font-display text-2xl font-bold">{{ $t('offline.title') }}</h1>
    <p class="text-ink-muted">{{ $t('offline.text') }}</p>
    <button
      type="button"
      :disabled="trying"
      class="mt-2 min-h-11 rounded-lg bg-accent px-6 font-semibold text-on-accent disabled:opacity-60"
      @click="retry"
    >
      {{ trying ? $t('common.loading') : $t('common.retry') }}
    </button>
  </main>
</template>
