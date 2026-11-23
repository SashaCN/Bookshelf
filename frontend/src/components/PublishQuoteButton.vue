<script setup lang="ts">
defineProps<{ quoteId: number }>()
</script>

<template>
  <RouterLink
    :to="{ name: 'community-new', query: { quote: String(quoteId) } }"
    class="inline-flex min-h-11 items-center rounded-lg border border-line bg-surface px-3 text-sm font-medium"
  >
    {{ $t('social.publish.action') }}
  </RouterLink>
</template>
