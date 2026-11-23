<script setup lang="ts">
import type { Reader } from '@/types/social'
import ReaderAvatar from './ReaderAvatar.vue'

defineProps<{ reader: Reader }>()
</script>

<template>
  <div class="flex items-center gap-3 rounded-2xl border border-line bg-surface p-3">
    <RouterLink :to="{ name: 'reader', params: { handle: reader.handle } }" class="flex min-w-0 flex-1 items-center gap-3">
      <ReaderAvatar :name="reader.name" />
      <span class="flex min-w-0 flex-col">
        <span class="truncate font-semibold">
          {{ reader.name }}
          <span v-if="reader.is_me" class="font-normal text-ink-muted">({{ $t('social.search.you') }})</span>
        </span>
        <span class="truncate text-sm text-ink-muted">@{{ reader.handle }}</span>
        <span v-if="reader.bio" class="line-clamp-2 text-sm">{{ reader.bio }}</span>
      </span>
    </RouterLink>
    <slot />
  </div>
</template>
