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

/** A book of several megabytes that is read and cleaned up on the server needs far longer than a JSON request does. */
export const UPLOAD_TIMEOUT_MS = 120_000

/** `fetch` that tells a missing network (or no answer in time) apart from an answer of the server. */
async function send(input: string, init: RequestInit, timeoutMs = TIMEOUT_MS): Promise<Response> {
  try {
    const signal = typeof AbortSignal.timeout === 'function' ? AbortSignal.timeout(timeoutMs) : undefined
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

export interface RequestOptions {
  /** How long to wait for an answer instead of the usual. */
  timeoutMs?: number
  /**
   * Let the request finish although the page is being left (the phone is locked, the tab is closed). The browser allows
   * only a small body for such a request (64 KB), which is for a few numbers and not for a form with a file.
   */
  keepalive?: boolean
}

async function request<T>(method: string, path: string, body?: unknown, options: RequestOptions = {}, retried = false): Promise<T> {
  const isWrite = method !== 'GET'
  // A file goes as it is: the browser sets the content type of a form, with the boundary that tells its parts apart.
  const isForm = typeof FormData !== 'undefined' && body instanceof FormData

  if (isWrite) {
    await ensureCsrfCookie()
  }

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }

  if (body !== undefined && !isForm) {
    headers['Content-Type'] = 'application/json'
  }

  const csrfToken = isWrite ? readCookie('XSRF-TOKEN') : null
  if (csrfToken) {
    headers['X-XSRF-TOKEN'] = csrfToken
  }

  const response = await send(
    path,
    {
      method,
      headers,
      credentials: 'same-origin',
      body: body === undefined ? undefined : isForm ? (body as FormData) : JSON.stringify(body),
      ...(options.keepalive ? { keepalive: true } : {}),
    },
    options.timeoutMs,
  )

  // The CSRF token expired (e.g. the session was regenerated): fetch a fresh one and retry once.
  if (response.status === 419 && !retried) {
    await ensureCsrfCookie(true)
    return request<T>(method, path, body, options, true)
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
  get: <T>(path: string, options?: RequestOptions) => request<T>('GET', path, undefined, options),
  post: <T>(path: string, body?: unknown, options?: RequestOptions) => request<T>('POST', path, body, options),
  patch: <T>(path: string, body?: unknown, options?: RequestOptions) => request<T>('PATCH', path, body, options),
  put: <T>(path: string, body?: unknown, options?: RequestOptions) => request<T>('PUT', path, body, options),
  delete: <T>(path: string, options?: RequestOptions) => request<T>('DELETE', path, undefined, options),
  /** Send a file (a `FormData`) with a POST; waits longer for the answer than the other methods. */
  upload: <T>(path: string, form: FormData, timeoutMs = UPLOAD_TIMEOUT_MS) => request<T>('POST', path, form, { timeoutMs }),
}
