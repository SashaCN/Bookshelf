<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

defineProps<{ title: string; backTo?: { name: string } }>()

const auth = useAuthStore()
const initials = computed(() => auth.user?.name.trim().charAt(0).toUpperCase() ?? '')
</script>

<template>
  <header class="flex items-center gap-2 py-4">
    <RouterLink
      v-if="backTo"
      :to="backTo"
      class="-ml-2 grid size-11 place-items-center rounded-full text-ink-muted hover:bg-sunken"
      :aria-label="$t('common.back')"
    >
      <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M15 5l-7 7 7 7" />
      </svg>
    </RouterLink>

    <h1 class="min-w-0 flex-1 truncate font-display text-3xl font-bold tracking-tight">{{ title }}</h1>

    <slot name="action" />

    <RouterLink
      :to="{ name: 'profile' }"
      class="grid size-10 shrink-0 place-items-center rounded-full bg-sunken text-sm font-semibold"
      :aria-label="$t('profile.title')"
    >
      {{ initials }}
    </RouterLink>
  </header>
</template>
