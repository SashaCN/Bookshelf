<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import AuthLayout from '@/components/AuthLayout.vue'
import FormField from '@/components/FormField.vue'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const submitting = ref(false)
const error = ref('')

async function submit() {
  submitting.value = true
  error.value = ''

  try {
    await auth.login({ email: email.value, password: password.value })
    await router.push({ name: 'home' })
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      error.value = t('auth.errors.invalidCredentials')
    } else if (e instanceof ApiError && e.status === 429) {
      error.value = t('auth.errors.tooManyAttempts')
    } else {
      error.value = t('auth.errors.generic')
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthLayout :title="$t('auth.login.title')">
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <FormField id="email" v-model="email" type="email" autocomplete="email" :label="$t('auth.fields.email')" />
      <FormField
        id="password"
        v-model="password"
        type="password"
        autocomplete="current-password"
        :label="$t('auth.fields.password')"
      />

      <p v-if="error" role="alert" class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{{ error }}</p>

      <button
        type="submit"
        :disabled="submitting"
        class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      >
        {{ $t('auth.login.submit') }}
      </button>
    </form>

    <template #footer>
      {{ $t('auth.login.noAccount') }}
      <RouterLink :to="{ name: 'register' }" class="font-medium text-accent underline underline-offset-2">
        {{ $t('auth.login.toRegister') }}
      </RouterLink>
    </template>
  </AuthLayout>
</template>
