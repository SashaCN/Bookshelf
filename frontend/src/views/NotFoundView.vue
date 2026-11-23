<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue'
import PageHeader from '@/components/PageHeader.vue'
</script>

<template>
  <PageHeader :title="$t('notFound.title')" />

  <EmptyState :title="$t('notFound.heading')" :text="$t('notFound.text')">
    <RouterLink
      :to="{ name: 'home' }"
      class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 font-semibold text-on-accent"
    >
      {{ $t('notFound.home') }}
    </RouterLink>
  </EmptyState>
</template>
