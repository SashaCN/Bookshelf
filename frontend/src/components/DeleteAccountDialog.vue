<script setup lang="ts">
import { ref } from 'vue'
import BaseDialog from '@/components/BaseDialog.vue'
import FormField from '@/components/FormField.vue'

const props = defineProps<{
  pending: boolean
  error: string
  passwordError: string
}>()

const emit = defineEmits<{
  cancel: []
  confirm: [password: string]
}>()

const password = ref('')

function submit() {
  if (!password.value || props.pending) return
  emit('confirm', password.value)
}
</script>

<template>
  <BaseDialog :title="$t('account.danger.dialogTitle')" initial-focus="input" @cancel="emit('cancel')">
    <template #default="{ descriptionId }">
      <div :id="descriptionId" class="flex flex-col gap-2 text-sm">
        <p>{{ $t('account.danger.dialogIntro') }}</p>
        <ul class="list-disc pl-5">
          <li v-for="item in ['library', 'quotes', 'community', 'books', 'follows']" :key="item">
            {{ $t(`account.danger.items.${item}`) }}
          </li>
        </ul>
        <p class="font-medium">{{ $t('account.danger.irreversible') }}</p>
      </div>

      <form class="flex flex-col gap-4" @submit.prevent="submit">
        <p v-if="error" role="alert" class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{{ error }}</p>

        <FormField
          id="delete-account-password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          :label="$t('account.danger.password')"
          :error="passwordError"
        />

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button
            type="button"
            class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium"
            @click="emit('cancel')"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="pending"
            class="min-h-11 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
          >
            {{ $t('account.danger.confirm') }}
          </button>
        </div>
      </form>
    </template>
  </BaseDialog>
</template>
