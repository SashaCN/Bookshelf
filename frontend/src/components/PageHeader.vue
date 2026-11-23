<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

defineProps<{ title: string; backTo?: { name: string; params?: Record<string, string> } }>()

const auth = useAuthStore()
const newFollowers = computed(() => auth.user?.new_followers_count ?? 0)
/** The server caps the count, so the top value means "at least". */
const badgeText = computed(() => (newFollowers.value >= 99 ? '99+' : String(newFollowers.value)))
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

    <h1 tabindex="-1" class="min-w-0 flex-1 truncate font-display text-3xl font-bold tracking-tight outline-none">{{ title }}</h1>

    <slot name="action" />

    <RouterLink
      v-if="auth.isAuthenticated"
      :to="{ name: 'profile' }"
      class="relative grid size-11 shrink-0 place-items-center rounded-full bg-sunken text-sm font-semibold"
      :aria-label="newFollowers > 0 ? `${$t('profile.title')}, ${$t('social.newFollowers', newFollowers)}` : $t('profile.title')"
    >
      {{ initials }}
      <span
        v-if="newFollowers > 0"
        data-test="new-followers-badge"
        class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-accent px-1 text-xs font-semibold leading-none text-on-accent"
        :aria-label="$t('social.newFollowers', newFollowers)"
      >{{ badgeText }}</span>
    </RouterLink>
  </header>
</template>
