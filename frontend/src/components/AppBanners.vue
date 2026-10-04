<script setup lang="ts">
import { useOnline } from '@/pwa/online'
import { applyUpdate, updateReady } from '@/pwa/updates'

const { online } = useOnline()
</script>

<template>
  <div v-if="!online || updateReady" class="sticky top-0 z-20 -mx-4 flex flex-col">
    <p v-if="!online" role="status" class="bg-danger-soft px-4 py-2 text-center text-sm text-danger">
      {{ $t('banners.offline') }}
    </p>

    <div v-if="updateReady" role="status" class="flex items-center justify-between gap-3 bg-accent px-4 py-2 text-sm text-on-accent">
      <span>{{ $t('banners.update') }}</span>
      <button type="button" class="min-h-9 shrink-0 rounded-md bg-on-accent/15 px-3 font-semibold" @click="applyUpdate">
        {{ $t('banners.updateAction') }}
      </button>
    </div>
  </div>
</template>
