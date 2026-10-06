<script setup lang="ts">
defineProps<{
  title: string
  /** A line under the title: where in the book the reader is. */
  subtitle: string
  backTo: { name: string; params: Record<string, string | number> }
}>()

defineEmits<{ contents: []; settings: [] }>()
</script>

<template>
  <header class="reader-bar flex items-center gap-1 border-b px-2 pt-[env(safe-area-inset-top)]">
    <RouterLink :to="backTo" :aria-label="$t('reader.topBar.back')" class="reader-icon grid size-11 shrink-0 place-items-center rounded-full">
      <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M15 5l-7 7 7 7" />
      </svg>
    </RouterLink>

    <div class="min-w-0 flex-1 px-1 py-1.5">
      <p class="truncate text-sm font-semibold leading-tight">{{ title }}</p>
      <p class="reader-muted truncate text-xs leading-tight">{{ subtitle }}</p>
    </div>

    <button type="button" :aria-label="$t('reader.topBar.contents')" class="reader-icon grid size-11 shrink-0 place-items-center rounded-full" @click="$emit('contents')">
      <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" />
      </svg>
    </button>

    <button type="button" :aria-label="$t('reader.topBar.settings')" class="reader-icon grid size-11 shrink-0 place-items-center rounded-full" @click="$emit('settings')">
      <span class="font-display text-lg font-bold leading-none" aria-hidden="true">Aa</span>
    </button>
  </header>
</template>
