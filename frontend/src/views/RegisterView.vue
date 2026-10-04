<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import AuthLayout from '@/components/AuthLayout.vue'
import FormField from '@/components/FormField.vue'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const name = ref('')
const email = ref('')
const password = ref('')
const submitting = ref(false)
const error = ref('')
const fieldErrors = reactive<{ name?: string; email?: string; password?: string }>({})

// Streaks and daily stats are counted in the reader's own time zone, so capture it at sign-up.
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone

async function submit() {
  submitting.value = true
  error.value = ''
  delete fieldErrors.name
  delete fieldErrors.email
  delete fieldErrors.password

  try {
    await auth.register({ name: name.value, email: email.value, password: password.value, timezone })
    await router.push({ name: 'home' })
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      const errors = e.fieldErrors
      if (errors.email) fieldErrors.email = t('auth.errors.emailTaken')
      if (errors.password) fieldErrors.password = t('auth.errors.passwordTooShort')
      if (errors.name) fieldErrors.name = errors.name[0]
      if (!errors.email && !errors.password && !errors.name) error.value = t('auth.errors.generic')
    } else {
      error.value = t('auth.errors.generic')
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthLayout :title="$t('auth.register.title')">
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <FormField id="name" v-model="name" autocomplete="name" :label="$t('auth.fields.name')" :error="fieldErrors.name" />
      <FormField
        id="email"
        v-model="email"
        type="email"
        autocomplete="email"
        :label="$t('auth.fields.email')"
        :error="fieldErrors.email"
      />
      <FormField
        id="password"
        v-model="password"
        type="password"
        autocomplete="new-password"
        :minlength="8"
        :label="$t('auth.fields.password')"
        :hint="$t('auth.hints.password')"
        :error="fieldErrors.password"
      />

      <p v-if="error" role="alert" class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{{ error }}</p>

      <button
        type="submit"
        :disabled="submitting"
        class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      >
        {{ $t('auth.register.submit') }}
      </button>
    </form>

    <template #footer>
      {{ $t('auth.register.haveAccount') }}
      <RouterLink :to="{ name: 'login' }" class="font-medium text-accent underline underline-offset-2">
        {{ $t('auth.register.toLogin') }}
      </RouterLink>
    </template>
  </AuthLayout>
</template>
