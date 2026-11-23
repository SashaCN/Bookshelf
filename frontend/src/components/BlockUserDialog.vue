<script setup lang="ts">
import BaseDialog from '@/components/BaseDialog.vue'

defineProps<{
  username: string
  pending: boolean
  error: string
}>()

const emit = defineEmits<{
  cancel: []
  confirm: []
}>()
</script>

<template>
  <BaseDialog
    role="alertdialog"
    initial-focus="button"
    :title="$t('social.profile.blockTitle', { handle: '@' + username })"
    @cancel="emit('cancel')"
  >
    <template #default="{ descriptionId }">
      <div :id="descriptionId" class="flex flex-col gap-2 text-sm">
        <p>{{ $t('social.profile.blockIntro') }}</p>
        <ul class="list-disc pl-5">
          <li v-for="item in ['hidden', 'follows', 'likes', 'silent']" :key="item">
            {{ $t(`social.profile.blockEffects.${item}`) }}
          </li>
        </ul>
        <p class="font-medium">{{ $t('social.profile.blockUndo') }}</p>
      </div>

      <p v-if="error" role="alert" class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{{ error }}</p>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium" @click="emit('cancel')">
          {{ $t('common.cancel') }}
        </button>
        <button
          type="button"
          data-test="confirm-block"
          :disabled="pending"
          class="min-h-11 rounded-lg bg-danger px-4 font-semibold text-on-accent disabled:opacity-60"
          @click="emit('confirm')"
        >
          {{ $t('social.profile.blockConfirm') }}
        </button>
      </div>
    </template>
  </BaseDialog>
</template>
