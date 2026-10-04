<script setup lang="ts">
import { useInstall } from '@/pwa/install'

const { canPrompt, showIosHint, install } = useInstall()
</script>

<template>
  <section v-if="canPrompt || showIosHint" class="flex flex-col gap-2 rounded-2xl border border-line bg-surface p-4">
    <h2 class="font-display text-xl font-semibold">{{ $t('install.title') }}</h2>
    <p class="text-sm text-ink-muted">{{ $t('install.text') }}</p>

    <button
      v-if="canPrompt"
      type="button"
      class="mt-1 min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent"
      @click="install"
    >
      {{ $t('install.action') }}
    </button>
    <p v-else class="text-sm">{{ $t('install.ios') }}</p>
  </section>
</template>
