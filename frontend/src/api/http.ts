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

let unauthorizedHandler: (() => void) | null = null

/** Called whenever the API answers 401, i.e. the session is gone (expired, or ended in another tab). */
export function onUnauthorized(handler: (() => void) | null): void {
  unauthorizedHandler = handler
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

  await fetch('/api/sanctum/csrf-cookie', {
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

  // FormData gets its multipart Content-Type (with the boundary) from the browser.
  const isForm = body instanceof FormData

  if (body !== undefined && !isForm) {
    headers['Content-Type'] = 'application/json'
  }

  const csrfToken = isWrite ? readCookie('XSRF-TOKEN') : null
  if (csrfToken) {
    headers['X-XSRF-TOKEN'] = csrfToken
  }

  const response = await fetch(path, {
    method,
    headers,
    credentials: 'same-origin',
    body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
  })

  // The CSRF token expired (e.g. the session was regenerated): fetch a fresh one and retry once.
  if (response.status === 419 && !retried) {
    await ensureCsrfCookie(true)
    return request<T>(method, path, body, true)
  }

  // Only parse real JSON: a redirected HTML page (e.g. "already signed in") must not crash the client.
  const isJson = response.headers.get('content-type')?.includes('json') ?? false
  const data = isJson ? ((await response.json()) as unknown) : null

  if (response.status === 401) {
    unauthorizedHandler?.()
  }

  if (!response.ok) {
    throw new ApiError(response.status, data)
  }

  return data as T
}

/** GET a file (not JSON): the raw body as a Blob, with the same session and error handling as the JSON calls. */
async function download(path: string): Promise<Blob> {
  const response = await fetch(path, {
    method: 'GET',
    headers: { Accept: '*/*', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })

  if (response.status === 401) {
    unauthorizedHandler?.()
  }

  if (!response.ok) {
    const isJson = response.headers.get('content-type')?.includes('json') ?? false
    throw new ApiError(response.status, isJson ? ((await response.json()) as unknown) : null)
  }

  return response.blob()
}

export const http = {
  download,
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  delete: <T>(path: string, body?: unknown) => request<T>('DELETE', path, body),
}
