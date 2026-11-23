<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { apiErrorCode } from '@/api/errors'
import { ApiError } from '@/api/http'
import DeleteAccountDialog from '@/components/DeleteAccountDialog.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import FormField from '@/components/FormField.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useAuthStore } from '@/stores/auth'

const USERNAME_PATTERN = /^[a-z0-9_]{3,30}$/

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const errorMessage = useApiErrorMessage()

const name = ref(auth.user?.name ?? '')
const username = ref(auth.user?.username ?? '')
const isPublic = ref(auth.user?.is_public ?? false)
const timezone = ref(auth.user?.timezone ?? 'Europe/Kyiv')
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const usernameError = ref('')

// The reader's day boundary decides which day a page counts for, so the time zone is editable.
const timezones = computed(() => {
  const all = Intl.supportedValuesOf('timeZone')
  return all.includes(timezone.value) ? all : [timezone.value, ...all]
})

/** What the reader typed, in the form it is saved. */
const cleanUsername = computed(() => username.value.trim().toLowerCase())

/** Shown as soon as the reader has typed something, before any request is made. */
const formatError = computed(() =>
  cleanUsername.value && !USERNAME_PATTERN.test(cleanUsername.value) ? t('profile.usernameInvalid') : '',
)
const shownUsernameError = computed(() => formatError.value || usernameError.value)

/** Server answers about the username go next to its field; returns false when something else is to blame. */
function showUsernameError(e: unknown): boolean {
  if (!(e instanceof ApiError)) return false

  if (apiErrorCode(e) === 'profile.username_required') {
    usernameError.value = t('apiErrors.profile.username_required')
  } else if (e.status === 422 && e.fieldErrors.username?.length) {
    // The format is checked before sending, so what the server still refuses is a name somebody else has.
    usernameError.value = t('profile.usernameTaken')
  } else {
    return false
  }

  return true
}

async function save() {
  if (formatError.value || saving.value) return

  saving.value = true
  saved.value = false
  error.value = ''
  usernameError.value = ''

  try {
    await auth.updateProfile({
      name: name.value.trim(),
      timezone: timezone.value,
      username: cleanUsername.value || null,
      is_public: isPublic.value,
    })
    saved.value = true
  } catch (e) {
    if (!showUsernameError(e)) error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

const loggingOut = ref(false)
const logoutError = ref('')

async function logout() {
  if (loggingOut.value) return

  loggingOut.value = true
  logoutError.value = ''

  try {
    await auth.logout()
    await router.push({ name: 'login' })
  } catch (e) {
    // Still signed in: say so, instead of leaving the reader with a button that did nothing.
    logoutError.value = errorMessage(e)
  } finally {
    loggingOut.value = false
  }
}

const currentPassword = ref('')
const newPassword = ref('')
const newPasswordConfirmation = ref('')
const changingPassword = ref(false)
const passwordChanged = ref(false)
const passwordError = ref('')
const currentPasswordError = ref('')
const newPasswordError = ref('')

async function changePassword() {
  if (changingPassword.value) return

  passwordError.value = ''
  currentPasswordError.value = ''
  newPasswordError.value = ''
  passwordChanged.value = false

  if (newPassword.value !== newPasswordConfirmation.value) {
    newPasswordError.value = t('profile.password.mismatch')
    return
  }

  changingPassword.value = true

  try {
    await auth.changePassword({
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: newPasswordConfirmation.value,
    })
    currentPassword.value = ''
    newPassword.value = ''
    newPasswordConfirmation.value = ''
    passwordChanged.value = true
  } catch (e) {
    if (e instanceof ApiError && e.status === 422 && (e.fieldErrors.current_password || e.fieldErrors.password)) {
      if (e.fieldErrors.current_password) currentPasswordError.value = t('profile.password.currentWrong')
      if (e.fieldErrors.password) newPasswordError.value = t('profile.password.newInvalid')
    } else {
      passwordError.value = errorMessage(e)
    }
  } finally {
    changingPassword.value = false
  }
}

const deleteOpen = ref(false)
const deleting = ref(false)
const deleteError = ref('')
const deletePasswordError = ref('')

function openDelete() {
  deleteError.value = ''
  deletePasswordError.value = ''
  deleteOpen.value = true
}

async function deleteAccount(password: string) {
  if (deleting.value) return

  deleting.value = true
  deleteError.value = ''
  deletePasswordError.value = ''

  try {
    await auth.deleteAccount(password)
    await router.push({ name: 'login', query: { deleted: '1' } })
  } catch (e) {
    if (e instanceof ApiError && e.status === 422 && e.fieldErrors.password) {
      deletePasswordError.value = t('profile.danger.passwordWrong')
    } else {
      deleteError.value = errorMessage(e)
    }
  } finally {
    deleting.value = false
  }
}

function closeDelete() {
  if (!deleting.value) deleteOpen.value = false
}
</script>

<template>
  <PageHeader :title="$t('profile.title')" :back-to="{ name: 'home' }" />

  <RouterLink
    v-if="auth.user?.username && auth.user.new_followers_count > 0"
    :to="{ name: 'followers', params: { username: auth.user.username } }"
    data-test="new-followers-line"
    class="mb-4 flex min-h-11 items-center rounded-2xl border border-accent bg-surface px-4 py-3 font-medium"
  >
    {{ $t('profile.newFollowers', auth.user.new_followers_count) }}
  </RouterLink>

  <form class="flex flex-col gap-4" @submit.prevent="save" @input="saved = false" @change="saved = false">
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

    <div class="flex flex-col gap-1.5">
      <label for="profile-username" class="text-sm font-medium">{{ $t('profile.username') }}</label>
      <input
        id="profile-username"
        v-model="username"
        type="text"
        maxlength="30"
        autocapitalize="none"
        autocomplete="username"
        spellcheck="false"
        :aria-invalid="shownUsernameError ? 'true' : undefined"
        :aria-describedby="shownUsernameError ? 'profile-username-error' : 'profile-username-hint'"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
        @input="usernameError = ''"
      >
      <p v-if="shownUsernameError" id="profile-username-error" class="text-sm text-danger">{{ shownUsernameError }}</p>
      <p v-else id="profile-username-hint" class="text-sm text-ink-muted">{{ $t('profile.usernameHint') }}</p>
    </div>

    <div class="flex items-start gap-3">
      <input
        id="profile-public"
        v-model="isPublic"
        type="checkbox"
        aria-describedby="profile-public-hint"
        class="mt-1 size-5 accent-accent"
      >
      <div class="flex flex-col">
        <label for="profile-public" class="text-sm font-medium">{{ $t('profile.isPublic') }}</label>
        <p id="profile-public-hint" class="text-sm text-ink-muted">{{ $t('profile.isPublicHint') }}</p>
      </div>
    </div>

    <RouterLink
      v-if="auth.user?.username && auth.user.is_public"
      :to="{ name: 'public-profile', params: { username: auth.user.username } }"
      class="text-sm font-medium text-accent underline-offset-2 hover:underline"
    >
      {{ $t('profile.publicLink') }}
    </RouterLink>

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
    <ErrorNotice v-if="logoutError" :message="logoutError" class="mb-3" />
    <button
      type="button"
      :disabled="loggingOut"
      class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
      @click="logout"
    >
      {{ $t('profile.logout') }}
    </button>
  </div>

  <section class="mt-8 border-t border-line pt-4" aria-labelledby="profile-password-title">
    <h2 id="profile-password-title" class="mb-3 text-lg font-semibold">{{ $t('profile.password.title') }}</h2>
    <form class="flex flex-col gap-4" data-test="password-form" @submit.prevent="changePassword" @input="passwordChanged = false">
      <ErrorNotice v-if="passwordError" :message="passwordError" />
      <FormField
        id="profile-current-password"
        v-model="currentPassword"
        type="password"
        autocomplete="current-password"
        :label="$t('profile.password.current')"
        :error="currentPasswordError"
      />
      <FormField
        id="profile-new-password"
        v-model="newPassword"
        type="password"
        autocomplete="new-password"
        :label="$t('profile.password.new')"
        :hint="$t('auth.hints.password')"
        :error="newPasswordError"
      />
      <FormField
        id="profile-confirm-password"
        v-model="newPasswordConfirmation"
        type="password"
        autocomplete="new-password"
        :label="$t('profile.password.confirm')"
      />
      <button
        type="submit"
        :disabled="changingPassword"
        class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      >
        {{ $t('profile.password.submit') }}
      </button>
      <p v-if="passwordChanged" role="status" class="text-sm text-ink-muted">{{ $t('profile.password.done') }}</p>
    </form>
  </section>

  <section class="mt-8 border-t border-line pt-4" aria-labelledby="profile-danger-title">
    <h2 id="profile-danger-title" class="mb-1 text-lg font-semibold text-danger">{{ $t('profile.danger.title') }}</h2>
    <p class="mb-3 text-sm text-ink-muted">{{ $t('profile.danger.text') }}</p>
    <button
      type="button"
      data-test="delete-account"
      class="min-h-11 rounded-lg border border-danger bg-surface px-4 font-medium text-danger"
      @click="openDelete"
    >
      {{ $t('profile.danger.open') }}
    </button>
  </section>

  <DeleteAccountDialog
    v-if="deleteOpen"
    :pending="deleting"
    :error="deleteError"
    :password-error="deletePasswordError"
    @cancel="closeDelete"
    @confirm="deleteAccount"
  />
</template>
