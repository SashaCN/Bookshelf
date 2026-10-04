<script setup lang="ts">
function reload() {
  window.location.reload()
}
</script>

<template>
  <div role="alert" class="flex flex-col items-center gap-3 py-16 text-center">
    <p class="font-display text-2xl font-bold">{{ $t('crash.title') }}</p>
    <p class="text-ink-muted">{{ $t('crash.text') }}</p>
    <div class="mt-2 flex gap-2">
      <button
        type="button"
        class="min-h-11 rounded-lg bg-accent px-5 font-semibold text-on-accent"
        @click="reload"
      >
        {{ $t('crash.reload') }}
      </button>
      <RouterLink :to="{ name: 'home' }" class="inline-flex min-h-11 items-center rounded-lg border border-line bg-surface px-5 font-medium">
        {{ $t('crash.home') }}
      </RouterLink>
    </div>
  </div>
</template>
