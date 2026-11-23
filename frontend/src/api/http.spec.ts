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

  it('sends the body of a DELETE when one is given, and none otherwise', async () => {
    document.cookie = 'XSRF-TOKEN=token'
    fetchMock.mockImplementation(async () => jsonResponse(204))

    await http.delete('/api/me', { body: { password: 'secret' } })
    expect(fetchMock.mock.calls.at(-1)![1]!.body).toBe('{"password":"secret"}')

    await http.delete('/api/quotes/1')
    expect(fetchMock.mock.calls.at(-1)![1]!.body).toBeUndefined()
  })

  describe('a request that has to outlive the page', () => {
    function lastInit(): RequestInit {
      return fetchMock.mock.calls.at(-1)![1]!
    }

    beforeEach(() => {
      document.cookie = 'XSRF-TOKEN=token'
      fetchMock.mockImplementation(async () => jsonResponse(200, { ok: true }))
    })

    it.each([
      ['put', () => http.put('/api/x', { a: 1 }, { keepalive: true })],
      ['post', () => http.post('/api/x', { a: 1 }, { keepalive: true })],
      ['patch', () => http.patch('/api/x', { a: 1 }, { keepalive: true })],
      ['get', () => http.get('/api/x', { keepalive: true })],
      ['delete', () => http.delete('/api/x', { keepalive: true })],
    ])('can be asked for with %s', async (_method, send) => {
      await expect(send()).resolves.toEqual({ ok: true })

      expect(lastInit().keepalive).toBe(true)
    })

    it('is not asked for unless it is wanted, so every other call is as it was', async () => {
      await http.put('/api/x', { a: 1 })
      expect(lastInit()).not.toHaveProperty('keepalive')

      await http.put('/api/x', { a: 1 }, {})
      expect(lastInit()).not.toHaveProperty('keepalive')

      await http.put('/api/x', { a: 1 }, { keepalive: false })
      expect(lastInit()).not.toHaveProperty('keepalive')

      await http.get('/api/x')
      expect(lastInit()).not.toHaveProperty('keepalive')
    })

    it('keeps the body, the CSRF token and the timeout of an ordinary request', async () => {
      const timeout = vi.spyOn(AbortSignal, 'timeout')

      await http.put('/api/x', { a: 1 }, { keepalive: true })

      const init = lastInit()
      expect(init.method).toBe('PUT')
      expect(init.body).toBe('{"a":1}')
      expect((init.headers as Record<string, string>)['X-XSRF-TOKEN']).toBe('token')
      expect(timeout).toHaveBeenLastCalledWith(20_000)
      timeout.mockRestore()
    })

    it('is sent again with keepalive after a 419', async () => {
      fetchMock.mockReset()
      fetchMock.mockResolvedValueOnce(jsonResponse(419, { message: 'CSRF token mismatch.' }))
      fetchMock.mockResolvedValueOnce(jsonResponse(204))
      fetchMock.mockResolvedValueOnce(jsonResponse(200, { ok: true }))

      await http.put('/api/x', { a: 1 }, { keepalive: true })

      expect(fetchMock.mock.calls.map((call) => call[0])).toEqual(['/api/x', '/api/sanctum/csrf-cookie', '/api/x'])
      expect(fetchMock.mock.calls[0]![1]!.keepalive).toBe(true)
      expect(fetchMock.mock.calls[2]![1]!.keepalive).toBe(true)
    })
  })

  describe('uploading a file', () => {
    function lastInit(): RequestInit {
      return fetchMock.mock.calls.at(-1)![1]!
    }

    it('sends the form as it is, without a content type of its own, and with the CSRF token', async () => {
      document.cookie = 'XSRF-TOKEN=token'
      fetchMock.mockResolvedValueOnce(jsonResponse(201, { data: 1 }))
      const form = new FormData()
      form.append('file', new File(['x'], 'book.epub'))

      await expect(http.upload('/api/library/1/text', form)).resolves.toEqual({ data: 1 })

      const init = lastInit()
      expect(fetchMock.mock.calls.at(-1)![0]).toBe('/api/library/1/text')
      expect(init.method).toBe('POST')
      expect(init.body).toBe(form)
      expect(init.headers).not.toHaveProperty('Content-Type')
      expect((init.headers as Record<string, string>)['X-XSRF-TOKEN']).toBe('token')
    })

    it('waits longer for the answer than for the others, or as long as it is told', async () => {
      const timeout = vi.spyOn(AbortSignal, 'timeout')
      document.cookie = 'XSRF-TOKEN=token'
      fetchMock.mockImplementation(async () => jsonResponse(200, {}))

      await http.get('/api/me')
      await http.upload('/api/library/1/text', new FormData())
      await http.upload('/api/library/1/text', new FormData(), 5_000)

      expect(timeout.mock.calls.map((call) => call[0])).toEqual([20_000, 120_000, 5_000])
      timeout.mockRestore()
    })

    it('gives up on a book that takes too long and says the network is missing', async () => {
      document.cookie = 'XSRF-TOKEN=token'
      fetchMock.mockRejectedValueOnce(new DOMException('The operation timed out.', 'TimeoutError'))

      await expect(http.upload('/api/library/1/text', new FormData())).rejects.toBeInstanceOf(NetworkError)
    })

    it('sends the same form again with a fresh CSRF token after a 419', async () => {
      document.cookie = 'XSRF-TOKEN=stale'
      const form = new FormData()
      fetchMock.mockResolvedValueOnce(jsonResponse(419, { message: 'CSRF token mismatch.' }))
      fetchMock.mockResolvedValueOnce(jsonResponse(204))
      fetchMock.mockResolvedValueOnce(jsonResponse(201, { ok: true }))

      await expect(http.upload('/api/library/1/text', form)).resolves.toEqual({ ok: true })

      expect(fetchMock.mock.calls.map((call) => call[0])).toEqual(['/api/library/1/text', '/api/sanctum/csrf-cookie', '/api/library/1/text'])
      expect(lastInit().body).toBe(form)
    })

    it('throws ApiError with the validation errors, and one without a body for a proxy that turns a big file away', async () => {
      document.cookie = 'XSRF-TOKEN=token'
      fetchMock.mockResolvedValueOnce(jsonResponse(422, { message: 'reader.drm', errors: { file: ['reader.drm'] } }))
      fetchMock.mockResolvedValueOnce(new Response('<html>413</html>', { status: 413, headers: { 'Content-Type': 'text/html' } }))

      const invalid = await http.upload('/api/library/1/text', new FormData()).catch((e: unknown) => e)
      const tooBig = await http.upload('/api/library/1/text', new FormData()).catch((e: unknown) => e)

      expect((invalid as ApiError).fieldErrors).toEqual({ file: ['reader.drm'] })
      expect((tooBig as ApiError).status).toBe(413)
    })

    it('still sends JSON as JSON', async () => {
      document.cookie = 'XSRF-TOKEN=token'
      fetchMock.mockResolvedValueOnce(jsonResponse(200, {}))

      await http.put('/api/book-texts/1/position', { chapter: 1 })

      expect((lastInit().headers as Record<string, string>)['Content-Type']).toBe('application/json')
      expect(lastInit().body).toBe('{"chapter":1}')
    })
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
