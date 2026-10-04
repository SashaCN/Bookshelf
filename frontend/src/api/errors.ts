import { ApiError } from './http'

/** Business-rule codes are "<area>.<reason>", e.g. "library.total_pages_required". */
export function isApiErrorCode(value: unknown): value is string {
  return typeof value === 'string' && /^(library|catalog|quotes|goals)\.[a-z_]+$/.test(value)
}

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

  return candidates.find(isApiErrorCode) ?? null
}
