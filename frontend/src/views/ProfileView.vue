<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { authApi } from '@/api/auth'
import { isApiErrorCode } from '@/api/errors'
import { ApiError } from '@/api/http'
import DeleteAccountDialog from '@/components/DeleteAccountDialog.vue'
import ErrorNotice from '@/components/ErrorNotice.vue'
import FormField from '@/components/FormField.vue'
import InstallCard from '@/components/InstallCard.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useApiErrorMessage } from '@/composables/useApiErrorMessage'
import { useAuthStore } from '@/stores/auth'

/** Revoking the link at once can cancel the download in some browsers. */
const REVOKE_DELAY_MS = 10_000
const BIO_MAX = 280
const appVersion = __APP_VERSION__

const { t, te } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const errorMessage = useApiErrorMessage()

const name = ref(auth.user?.name ?? '')
const handle = ref(auth.user?.handle ?? '')
const bio = ref(auth.user?.bio ?? '')
const handleError = ref('')
const bioError = ref('')
const timezone = ref(auth.user?.timezone ?? 'Europe/Kyiv')
const saving = ref(false)
const saved = ref(false)
const error = ref('')

// The reader's day boundary decides which day a page counts for, so the time zone is editable.
const timezones = computed(() => {
  const all = Intl.supportedValuesOf('timeZone')
  return all.includes(timezone.value) ? all : [timezone.value, ...all]
})

/** Show 422 answers next to the fields they are about. Returns false when no field is to blame. */
function showFieldErrors(e: ApiError): boolean {
  const handleMessages = e.fieldErrors.handle
  const bioMessages = e.fieldErrors.bio

  if (handleMessages?.length) {
    const code = handleMessages.find(isApiErrorCode)
    handleError.value = code && te(`apiErrors.${code}`) ? t(`apiErrors.${code}`) : t('social.errors.handle')
  }

  if (bioMessages?.length) {
    bioError.value = t('social.errors.bio')
  }

  return Boolean(handleMessages?.length || bioMessages?.length)
}

async function save() {
  if (saving.value) return

  saving.value = true
  saved.value = false
  error.value = ''
  handleError.value = ''
  bioError.value = ''

  try {
    await auth.updateProfile({
      name: name.value.trim(),
      timezone: timezone.value,
      handle: handle.value.trim(),
      bio: bio.value.trim() || null,
    })
    // The server keeps handles in lower case.
    handle.value = auth.user?.handle ?? handle.value
    saved.value = true
  } catch (e) {
    if (!(e instanceof ApiError && e.status === 422 && showFieldErrors(e))) {
      error.value = errorMessage(e)
    }
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
    newPasswordError.value = t('account.password.mismatch')
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
      if (e.fieldErrors.current_password) currentPasswordError.value = t('account.password.currentWrong')
      if (e.fieldErrors.password) newPasswordError.value = t('account.password.newInvalid')
    } else {
      passwordError.value = errorMessage(e)
    }
  } finally {
    changingPassword.value = false
  }
}

const exporting = ref(false)
const exportError = ref('')

async function downloadData() {
  if (exporting.value) return

  exporting.value = true
  exportError.value = ''

  try {
    const file = await authApi.exportData()
    const url = URL.createObjectURL(file)
    const link = document.createElement('a')
    link.href = url
    link.download = `bookshelf-export-${new Date().toLocaleDateString('sv-SE')}.json`
    document.body.append(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), REVOKE_DELAY_MS)
  } catch (e) {
    // The limit is three an hour, so the generic "try in a minute" would mislead.
    exportError.value = e instanceof ApiError && e.status === 429 ? t('account.data.tooMany') : errorMessage(e)
  } finally {
    exporting.value = false
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
      deletePasswordError.value = t('account.danger.passwordWrong')
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

  <form class="flex flex-col gap-4" @submit.prevent="save" @input="saved = false" @change="saved = false">
    <ErrorNotice v-if="error" :message="error" />

    <div class="flex flex-col gap-1.5">
      <label for="profile-name" class="text-sm font-medium">{{ $t('auth.fields.name') }}</label>
      <input
        id="profile-name"
        v-model="name"
        type="text"
        required
        maxlength="100"
        autocomplete="name"
        class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30"
      >
    </div>

    <div class="flex flex-col gap-1.5">
      <label for="profile-handle" class="text-sm font-medium">{{ $t('social.fields.handle') }}</label>
      <div class="flex items-center gap-2">
        <span class="text-ink-muted" aria-hidden="true">@</span>
        <input
          id="profile-handle"
          v-model="handle"
          type="text"
          required
          minlength="3"
          maxlength="30"
          autocapitalize="none"
          autocomplete="off"
          spellcheck="false"
          :aria-invalid="handleError ? 'true' : undefined"
          :aria-describedby="handleError ? 'profile-handle-error' : 'profile-handle-hint'"
          class="min-h-11 min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
        >
      </div>
      <p v-if="handleError" id="profile-handle-error" class="text-sm text-danger">{{ handleError }}</p>
      <p v-else id="profile-handle-hint" class="text-sm text-ink-muted">{{ $t('social.fields.handleHint') }}</p>
    </div>

    <div class="flex flex-col gap-1.5">
      <label for="profile-bio" class="text-sm font-medium">{{ $t('social.fields.bio') }}</label>
      <textarea
        id="profile-bio"
        v-model="bio"
        rows="3"
        :maxlength="BIO_MAX"
        :aria-invalid="bioError ? 'true' : undefined"
        :aria-describedby="bioError ? 'profile-bio-error' : 'profile-bio-hint'"
        class="rounded-lg border border-line bg-surface px-3 py-2 text-base outline-none focus:border-accent focus:ring-2 focus:ring-accent/30 aria-[invalid=true]:border-danger"
      />
      <p v-if="bioError" id="profile-bio-error" class="text-sm text-danger">{{ bioError }}</p>
      <p id="profile-bio-hint" class="flex justify-between gap-3 text-sm text-ink-muted">
        <span>{{ $t('social.fields.bioHint') }}</span>
        <span class="tabular-nums">{{ $t('social.fields.bioCounter', { count: bio.length, max: BIO_MAX }) }}</span>
      </p>
    </div>

    <div class="flex flex-col gap-1.5">
      <p class="text-sm font-medium">{{ $t('auth.fields.email') }}</p>
      <p class="min-h-11 content-center text-ink-muted">{{ auth.user?.email }}</p>
      <p class="text-sm text-ink-muted">{{ $t('social.fields.publicNote') }}</p>
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

  <nav class="mt-6 flex flex-col gap-1" :aria-label="$t('social.tab')">
    <RouterLink
      v-if="auth.user?.handle"
      :to="{ name: 'reader', params: { handle: auth.user.handle } }"
      class="inline-flex min-h-11 items-center font-medium text-accent underline underline-offset-2"
    >
      {{ $t('social.fields.viewPublic') }}
    </RouterLink>
    <RouterLink
      :to="{ name: 'community-blocked' }"
      class="inline-flex min-h-11 items-center font-medium text-accent underline underline-offset-2"
    >
      {{ $t('social.blocked.link') }}
    </RouterLink>
  </nav>

  <InstallCard class="mt-6" />

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
    <h2 id="profile-password-title" class="mb-3 text-lg font-semibold">{{ $t('account.password.title') }}</h2>
    <form class="flex flex-col gap-4" data-test="password-form" @submit.prevent="changePassword" @input="passwordChanged = false">
      <ErrorNotice v-if="passwordError" :message="passwordError" />
      <FormField
        id="profile-current-password"
        v-model="currentPassword"
        type="password"
        autocomplete="current-password"
        :label="$t('account.password.current')"
        :error="currentPasswordError"
      />
      <FormField
        id="profile-new-password"
        v-model="newPassword"
        type="password"
        autocomplete="new-password"
        :label="$t('account.password.new')"
        :hint="$t('auth.hints.password')"
        :error="newPasswordError"
      />
      <FormField
        id="profile-confirm-password"
        v-model="newPasswordConfirmation"
        type="password"
        autocomplete="new-password"
        :label="$t('account.password.confirm')"
      />
      <button
        type="submit"
        :disabled="changingPassword"
        class="min-h-11 rounded-lg bg-accent px-4 font-semibold text-on-accent disabled:opacity-60"
      >
        {{ $t('account.password.submit') }}
      </button>
      <p v-if="passwordChanged" role="status" class="text-sm text-ink-muted">{{ $t('account.password.done') }}</p>
    </form>
  </section>

  <section class="mt-8 border-t border-line pt-4" aria-labelledby="profile-data-title">
    <h2 id="profile-data-title" class="mb-1 text-lg font-semibold">{{ $t('account.data.title') }}</h2>
    <p id="profile-data-hint" class="mb-3 text-sm text-ink-muted">{{ $t('account.data.hint') }}</p>
    <ErrorNotice v-if="exportError" :message="exportError" class="mb-3" />
    <button
      type="button"
      data-test="export-data"
      :disabled="exporting"
      aria-describedby="profile-data-hint"
      class="min-h-11 rounded-lg border border-line bg-surface px-4 font-medium disabled:opacity-60"
      @click="downloadData"
    >
      {{ exporting ? $t('account.data.downloading') : $t('account.data.download') }}
    </button>
  </section>

  <section class="mt-8 border-t border-line pt-4" aria-labelledby="profile-danger-title">
    <h2 id="profile-danger-title" class="mb-1 text-lg font-semibold text-danger">{{ $t('account.danger.title') }}</h2>
    <p class="mb-3 text-sm text-ink-muted">{{ $t('account.danger.text') }}</p>
    <button
      type="button"
      data-test="delete-account"
      class="min-h-11 rounded-lg border border-danger bg-surface px-4 font-medium text-danger"
      @click="openDelete"
    >
      {{ $t('account.danger.open') }}
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

  <p class="mt-8 text-center text-sm text-ink-muted" data-test="app-version">{{ $t('profile.version', { version: appVersion }) }}</p>
</template>
