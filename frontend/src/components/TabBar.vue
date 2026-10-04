<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()

const tabs = [
  { name: 'home', labelKey: 'tabs.reading', icon: 'M3 5.5c2.5-1 5.5-1 9 1v13c-3.5-2-6.5-2-9-1zM21 5.5c-2.5-1-5.5-1-9 1v13c3.5-2 6.5-2 9-1z' },
  { name: 'library', labelKey: 'tabs.library', icon: 'M4 4h4v15H4zM10 7h4v12h-4zM15.6 7.4l3.3-.9 2.8 11.4-3.3.9zM3 21h18' },
] as const
</script>

<template>
  <nav
    class="fixed inset-x-0 bottom-0 z-10 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)]"
    :aria-label="$t('tabs.label')"
  >
    <ul class="mx-auto grid max-w-md grid-cols-2">
      <li v-for="tab in tabs" :key="tab.name">
        <RouterLink
          :to="{ name: tab.name }"
          class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs"
          :class="route.meta.tab === tab.name ? 'font-semibold text-accent' : 'text-ink-muted'"
          :aria-current="route.meta.tab === tab.name ? 'page' : undefined"
        >
          <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path :d="tab.icon" />
          </svg>
          {{ $t(tab.labelKey) }}
        </RouterLink>
      </li>
    </ul>
  </nav>
</template>
