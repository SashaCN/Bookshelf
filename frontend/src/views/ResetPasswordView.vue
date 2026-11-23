<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import AuthLayout from '@/components/AuthLayout.vue'
import FormField from '@/components/FormField.vue'

const props = defineProps<{ token: string }>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const password = ref('')
const passwordConfirmation = ref('')
const submitting = ref(false)
const error = ref('')
const fieldErrors = reactive<{ email?: string; password?: string }>({})

async function submit() {
  // Enter in a field can submit again while the first request is still on its way.
  if (submitting.value) return

  submitting.value = true
  error.value = ''
  delete fieldErrors.email
  delete fieldErrors.password

  try {
    await authApi.resetPassword({
      token: props.token,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    await router.push({ name: 'login', query: { reset: '1' } })
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      const errors = e.fieldErrors
      // Laravel reports a bad or expired token under the email field.
      if (errors.email) fieldErrors.email = t('account.errors.invalidResetLink')
      if (errors.password) {
        fieldErrors.password = password.value === passwordConfirmation.value ? t('auth.errors.passwordTooShort') : t('account.errors.passwordMismatch')
      }
      if (!errors.email && !errors.password) error.value = t('auth.errors.generic')
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
  <AuthLayout :title="$t('account.reset.title')">
    <form class="flex flex-col gap-4" @submit.prevent="submit">
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
        :label="$t('account.reset.newPassword')"
        :hint="$t('auth.hints.password')"
        :error="fieldErrors.password"
      />
      <FormField
        id="password-confirmation"
        v-model="passwordConfirmation"
        type="password"
        autocomplete="new-password"
        :minlength="8"
        :label="$t('account.reset.confirmPassword')"
      />

      <p v-if="error" role="alert" class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{{ error }}</p>

      <button
        type="submit"
        :disabled="submitting"
        class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      >
        {{ $t('account.reset.submit') }}
      </button>
    </form>
  </AuthLayout>
</template>
