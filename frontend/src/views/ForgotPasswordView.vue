<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import AuthLayout from '@/components/AuthLayout.vue'
import FormField from '@/components/FormField.vue'

const { t } = useI18n()

const email = ref('')
const submitting = ref(false)
const sent = ref(false)
const error = ref('')
const fieldErrors = reactive<{ email?: string }>({})

async function submit() {
  // Enter in a field can submit again while the first request is still on its way.
  if (submitting.value) return

  submitting.value = true
  error.value = ''
  delete fieldErrors.email

  try {
    await authApi.forgotPassword(email.value)
    // The server answers the same for unknown emails, and so does the screen.
    sent.value = true
  } catch (e) {
    if (e instanceof ApiError && e.status === 422 && e.fieldErrors.email) {
      fieldErrors.email = e.fieldErrors.email[0]
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
  <AuthLayout :title="$t('auth.forgot.title')">
    <p v-if="sent" role="status" data-test="reset-link-sent" class="rounded-lg bg-surface px-3 py-2 text-sm text-ink-muted">
      {{ $t('auth.forgot.sent') }}
    </p>

    <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
      <p class="text-sm text-ink-muted">{{ $t('auth.forgot.intro') }}</p>
      <FormField
        id="email"
        v-model="email"
        type="email"
        autocomplete="email"
        :label="$t('auth.fields.email')"
        :error="fieldErrors.email"
      />

      <p v-if="error" role="alert" class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{{ error }}</p>

      <button
        type="submit"
        :disabled="submitting"
        class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      >
        {{ $t('auth.forgot.submit') }}
      </button>
    </form>

    <template #footer>
      <RouterLink :to="{ name: 'login' }" class="font-medium text-accent underline underline-offset-2">
        {{ $t('auth.forgot.toLogin') }}
      </RouterLink>
    </template>
  </AuthLayout>
</template>
