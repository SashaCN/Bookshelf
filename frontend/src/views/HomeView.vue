<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col gap-6 px-4 py-6">
    <header class="flex items-center justify-between">
      <h1 class="font-display text-3xl font-bold tracking-tight">{{ $t('home.title') }}</h1>
      <button
        type="button"
        class="min-h-11 rounded-lg border border-line px-3 text-sm font-medium"
        @click="logout"
      >
        {{ $t('home.logout') }}
      </button>
    </header>

    <section class="rounded-2xl border border-line bg-surface p-5">
      <p class="font-semibold">{{ $t('home.greeting', { name: auth.user?.name }) }}</p>
      <p class="mt-1 text-ink-muted">{{ $t('home.placeholder') }}</p>
    </section>
  </main>
</template>
