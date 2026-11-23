import { beforeEach, describe, expect, it, vi } from 'vitest'
import { http } from './http'
import { statsApi } from './stats'

vi.mock('./http', () => ({
  http: { get: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

describe('statsApi', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('asks for the recap of a year and keeps the meta with the data', async () => {
    const answer = { data: { year: 2025 }, meta: { first_year: 2024, current_year: 2026 } }
    vi.mocked(http.get).mockResolvedValue(answer)

    const recap = await statsApi.year(2025)

    expect(http.get).toHaveBeenCalledWith('/api/stats/year/2025')
    expect(recap).toEqual(answer)
  })
})
