/** Error thrown for every non-2xx response; `fieldErrors` is filled for Laravel validation errors (422). */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly body: unknown,
  ) {
    super(`Request failed with status ${status}`)
    this.name = 'ApiError'
  }

  get fieldErrors(): Record<string, string[]> {
    const errors = (this.body as { errors?: unknown } | null)?.errors
    return errors && typeof errors === 'object' ? (errors as Record<string, string[]>) : {}
  }
}

/** The request never got an answer: no network, a dropped connection, a server that is down. */
export class NetworkError extends Error {
  constructor() {
    super('The server could not be reached')
    this.name = 'NetworkError'
  }
}

let unauthorizedHandler: (() => void) | null = null

/**
 * Called when the server says the reader is no longer signed in (an expired session) in answer to a request
 * made while using the app. Asking who is signed in, or signing in, are expected to answer 401 and do not count.
 */
export function onUnauthorized(handler: (() => void) | null): void {
  unauthorizedHandler = handler
}

/** How long to wait for an answer. A phone with one bar of signal may never get one, and waiting for ever helps nobody. */
const TIMEOUT_MS = 20_000

/** `fetch` that tells a missing network (or no answer in time) apart from an answer of the server. */
async function send(input: string, init: RequestInit): Promise<Response> {
  try {
    const signal = typeof AbortSignal.timeout === 'function' ? AbortSignal.timeout(TIMEOUT_MS) : undefined
    return await fetch(input, { ...init, signal })
  } catch {
    throw new NetworkError()
  }
}

function readCookie(name: string): string | null {
  const entry = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${name}=`))
  return entry ? decodeURIComponent(entry.slice(name.length + 1)) : null
}

/** Sanctum SPA auth: the CSRF cookie must exist before any state-changing request. */
async function ensureCsrfCookie(force = false): Promise<void> {
  if (!force && readCookie('XSRF-TOKEN')) {
    return
  }

  await send('/api/sanctum/csrf-cookie', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
}

async function request<T>(method: string, path: string, body?: unknown, retried = false): Promise<T> {
  const isWrite = method !== 'GET'

  if (isWrite) {
    await ensureCsrfCookie()
  }

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  const csrfToken = isWrite ? readCookie('XSRF-TOKEN') : null
  if (csrfToken) {
    headers['X-XSRF-TOKEN'] = csrfToken
  }

  const response = await send(path, {
    method,
    headers,
    credentials: 'same-origin',
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  // The CSRF token expired (e.g. the session was regenerated): fetch a fresh one and retry once.
  if (response.status === 419 && !retried) {
    await ensureCsrfCookie(true)
    return request<T>(method, path, body, true)
  }

  // Only parse real JSON: a redirected HTML page (e.g. "already signed in") must not crash the client.
  const isJson = response.headers.get('content-type')?.includes('json') ?? false
  const data = isJson ? ((await response.json()) as unknown) : null

  if (response.status === 401 && path !== '/api/me' && !path.startsWith('/api/auth/')) {
    unauthorizedHandler?.()
  }

  if (!response.ok) {
    throw new ApiError(response.status, data)
  }

  return data as T
}

export const http = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  delete: <T>(path: string) => request<T>('DELETE', path),
}
