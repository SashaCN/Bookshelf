<script setup lang="ts">
defineProps<{ title: string }>()
</script>

<template>
  <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center gap-8 px-4 py-10">
    <header class="flex flex-col gap-1">
      <p class="font-display text-4xl font-bold tracking-tight">{{ $t('app.name') }}</p>
      <p class="text-ink-muted">{{ $t('app.tagline') }}</p>
    </header>

    <section class="flex flex-col gap-5 rounded-2xl border border-line bg-surface p-5">
      <h1 class="text-xl font-semibold">{{ title }}</h1>
      <slot />
    </section>

    <footer class="text-center text-sm text-ink-muted">
      <slot name="footer" />
    </footer>
  </main>
</template>
