<script setup lang="ts">
import TabBar from './TabBar.vue'
</script>

<template>
  <div class="mx-auto min-h-dvh w-full max-w-md px-4 pb-24">
    <RouterView />
    <TabBar />
  </div>
</template>
