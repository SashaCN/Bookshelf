import { useI18n } from 'vue-i18n'
import { apiErrorCode } from '@/api/errors'
import { ApiError } from '@/api/http'

/** Turns an API failure into a message for the reader, in the interface language. */
export function useApiErrorMessage() {
  const { t, te } = useI18n()

  return (error: unknown): string => {
    const code = apiErrorCode(error)

    if (code && te(`apiErrors.${code}`)) {
      return t(`apiErrors.${code}`)
    }

    if (error instanceof ApiError && error.status === 429) {
      return t('apiErrors.tooManyRequests')
    }

    return t('apiErrors.generic')
  }
}
