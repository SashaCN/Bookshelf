<script setup lang="ts">
import { onErrorCaptured, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppBanners from './AppBanners.vue'
import CrashNotice from './CrashNotice.vue'
import TabBar from './TabBar.vue'

const route = useRoute()
const crashed = ref(false)

// A failure while drawing or running a screen must not leave a blank page: say so and offer a way out.
onErrorCaptured((error) => {
  console.error(error)
  crashed.value = true
  return false
})

// Going to another screen is a fresh start.
watch(
  () => route.path,
  () => {
    crashed.value = false
  },
)
</script>

<template>
  <div class="mx-auto min-h-dvh w-full max-w-md px-4 pb-24">
    <AppBanners />
    <CrashNotice v-if="crashed" />
    <RouterView v-else />
    <TabBar />
  </div>
</template>
