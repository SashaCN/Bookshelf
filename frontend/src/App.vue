<script setup lang="ts">
import BootScreen from '@/components/BootScreen.vue'
import OfflineGate from '@/components/OfflineGate.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <OfflineGate v-if="auth.unreachable" />
  <!-- The first answer of the server decides which screen opens: until then, a splash instead of a blank page. -->
  <BootScreen v-else-if="auth.initializing" />
  <RouterView v-else />
</template>
