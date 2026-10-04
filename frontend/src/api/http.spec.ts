import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, http, NetworkError, onUnauthorized } from './http'

function jsonResponse(status: number, body?: unknown): Response {
  return new Response(body === undefined ? null : JSON.stringify(body), {
    status,
    headers: body === undefined ? undefined : { 'Content-Type': 'application/json' },
  })
}

describe('http client', () => {
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    vi.stubGlobal('fetch', fetchMock)
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    fetchMock.mockReset()
    onUnauthorized(null)
  })

  it('does not fetch a CSRF cookie for GET requests', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: 1 }))

    await expect(http.get('/api/me')).resolves.toEqual({ data: 1 })

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/me')
  })

  it('fetches the CSRF cookie first and sends it back as a header on writes', async () => {
    fetchMock.mockImplementationOnce(async () => {
      document.cookie = 'XSRF-TOKEN=abc%3D123'
      return jsonResponse(204)
    })
    fetchMock.mockResolvedValueOnce(jsonResponse(201))

    await http.post('/api/auth/register', { name: 'Olena' })

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/sanctum/csrf-cookie')
    const init = fetchMock.mock.calls[1]?.[1]
    expect((init?.headers as Record<string, string>)['X-XSRF-TOKEN']).toBe('abc=123')
    expect(init?.body).toBe(JSON.stringify({ name: 'Olena' }))
  })

  it('retries once with a fresh CSRF cookie after a 419', async () => {
    document.cookie = 'XSRF-TOKEN=stale'
    fetchMock.mockResolvedValueOnce(jsonResponse(419, { message: 'CSRF token mismatch.' }))
    fetchMock.mockResolvedValueOnce(jsonResponse(204))
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { ok: true }))

    await expect(http.post('/api/auth/login', {})).resolves.toEqual({ ok: true })

    expect(fetchMock.mock.calls.map((call) => call[0])).toEqual([
      '/api/auth/login',
      '/api/sanctum/csrf-cookie',
      '/api/auth/login',
    ])
  })

  it('throws ApiError with field errors for validation failures', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(422, { message: 'Invalid', errors: { email: ['Taken'] } }))

    const error = await http.get('/api/me').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).fieldErrors).toEqual({ email: ['Taken'] })
  })

  it('ignores a non-JSON body instead of failing to parse it', async () => {
    fetchMock.mockResolvedValueOnce(new Response('<html></html>', { status: 200, headers: { 'Content-Type': 'text/html' } }))

    await expect(http.get('/api/me')).resolves.toBeNull()
  })

  describe('when there is no network', () => {
    it('gives up waiting for an answer after a while', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: 1 }))

      await http.get('/api/me')

      expect(fetchMock.mock.calls[0]?.[1]?.signal).toBeInstanceOf(AbortSignal)
    })

    it('takes a request that ran out of time for a missing network', async () => {
      fetchMock.mockRejectedValueOnce(new DOMException('The operation timed out.', 'TimeoutError'))

      await expect(http.get('/api/me')).rejects.toBeInstanceOf(NetworkError)
    })

    it('tells a missing network apart from an answer of the server', async () => {
      fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'))

      const error = await http.get('/api/me').catch((e: unknown) => e)

      expect(error).toBeInstanceOf(NetworkError)
      expect(error).not.toBeInstanceOf(ApiError)
    })

    it('does the same when the CSRF cookie of a write cannot be fetched', async () => {
      fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'))

      await expect(http.post('/api/library', {})).rejects.toBeInstanceOf(NetworkError)
    })
  })

  describe('when the session has ended', () => {
    it('lets the app know when a request made while using it answers 401', async () => {
      const handler = vi.fn()
      onUnauthorized(handler)
      fetchMock.mockResolvedValueOnce(jsonResponse(401, { message: 'Unauthenticated.' }))

      await expect(http.get('/api/library')).rejects.toBeInstanceOf(ApiError)

      expect(handler).toHaveBeenCalledTimes(1)
    })

    it.each(['/api/me', '/api/auth/login', '/api/auth/register'])('expects a 401 from %s and says nothing', async (path) => {
      const handler = vi.fn()
      onUnauthorized(handler)
      fetchMock.mockResolvedValueOnce(jsonResponse(401, { message: 'Unauthenticated.' }))

      await expect(http.get(path)).rejects.toBeInstanceOf(ApiError)

      expect(handler).not.toHaveBeenCalled()
    })

    it('does not mind when nobody listens', async () => {
      fetchMock.mockResolvedValueOnce(jsonResponse(401, { message: 'Unauthenticated.' }))

      await expect(http.get('/api/library')).rejects.toBeInstanceOf(ApiError)
    })
  })
})
