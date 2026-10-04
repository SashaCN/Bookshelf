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

  if (body !== undefined) {
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
