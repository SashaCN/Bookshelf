<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import ErrorNotice from '@/components/ErrorNotice.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const errorMessage = useApiErrorMessage()

const name = ref(auth.user?.name ?? '')
const timezone = ref(auth.user?.timezone ?? 'Europe/Kyiv')
const saving = ref(false)
const saved = ref(false)
const error = ref('')

// The reader's day boundary decides which day a page counts for, so the time zone is editable.
const timezones = computed(() => {
  const all = Intl.supportedValuesOf('timeZone')
  return all.includes(timezone.value) ? all : [timezone.value, ...all]
})

async function save() {
  saving.value = true
  saved.value = false
  error.value = ''

  try {
    await auth.updateProfile({ name: name.value.trim(), timezone: timezone.value })
    saved.value = true
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function logout() {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <PageHeader :title="$t('profile.title')" :back-to="{ name: 'home' }" />

  <form class="flex flex-col gap-4" @submit.prevent="save">
    <ErrorNotice v-if="error" :message="error" />

    <div class="flex flex-col gap-1.5">
      <label for="profile-name" class="text-sm font-medium">{{ $t('auth.fields.name') }}</label>
      <input
        id="profile-name"
        v-model="name"
        type="text"
        required
        maxlength="255"
        autocomplete="name"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
      >
    </div>

    <div class="flex flex-col gap-1.5">
      <p class="text-sm font-medium">{{ $t('auth.fields.email') }}</p>
      <p class="min-h-11 content-center text-ink-muted">{{ auth.user?.email }}</p>
    </div>

    <div class="flex flex-col gap-1.5">
      <label for="profile-timezone" class="text-sm font-medium">{{ $t('profile.timezone') }}</label>
      <select
        id="profile-timezone"
        v-model="timezone"
        aria-describedby="profile-timezone-hint"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
      >
        <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option>
      </select>
      <p id="profile-timezone-hint" class="text-sm text-ink-muted">{{ $t('profile.timezoneHint') }}</p>
    </div>

    <button
      type="submit"
      :disabled="saving"
      class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
    >
      {{ $t('common.save') }}
    </button>
    <p v-if="saved" role="status" class="text-sm text-ink-muted">{{ $t('profile.saved') }}</p>
  </form>

  <div class="mt-8 border-t border-line pt-4">
    <button
      type="button"
      class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium"
      @click="logout"
    >
      {{ $t('profile.logout') }}
    </button>
  </div>
</template>
