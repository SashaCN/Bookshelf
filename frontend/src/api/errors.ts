import { ApiError } from './http'

/**
 * Business-rule errors of the API are stable codes such as "library.total_pages_required",
 * either as the response message or as a validation message. Returns the first one found.
 */
export function apiErrorCode(error: unknown): string | null {
  if (!(error instanceof ApiError)) {
    return null
  }

  const candidates: unknown[] = [(error.body as { message?: unknown } | null)?.message]

  for (const messages of Object.values(error.fieldErrors)) {
    candidates.push(...messages)
  }

  const code = candidates.find(
    (candidate): candidate is string =>
      typeof candidate === 'string' && /^(library|catalog)\.[a-z_]+$/.test(candidate),
  )

  return code ?? null
}
