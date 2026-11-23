<script setup lang="ts">
import { nextTick, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import OfflineBanner from './OfflineBanner.vue'
import TabBar from './TabBar.vue'

const route = useRoute()
const auth = useAuthStore()

// A screen reader user gets no page load when the router swaps the screen: move focus to the new heading instead.
// Only a different page counts; changing a filter in the query string must not steal focus.
watch(
  () => route.path,
  async () => {
    await nextTick()
    document.querySelector<HTMLElement>('h1')?.focus({ preventScroll: true })
  },
)
</script>

<template>
  <div class="mx-auto min-h-dvh w-full max-w-md px-4 pb-24">
    <OfflineBanner />
    <RouterView />
    <!-- A guest can only be here on a public profile, which has no reader to navigate for. -->
    <TabBar v-if="auth.isAuthenticated" />
  </div>
</template>
