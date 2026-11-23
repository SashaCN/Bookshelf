import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { socialApi } from '@/api/social'
import { makeReader } from '@/test/socialFixtures'
import type { ReadersPage } from '@/types/social'
import { useBlocksStore } from './blocks'

vi.mock('@/api/social', () => ({
  socialApi: { blocks: vi.fn(), unblock: vi.fn() },
}))

function page(handles: string[], next: string | null = null): ReadersPage {
  return { data: handles.map((handle) => makeReader({ handle })), links: { next } }
}

describe('blocks store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('loads the first page of the blocked readers', async () => {
    vi.mocked(socialApi.blocks).mockResolvedValue(page(['a_reader', 'b_reader'], '/next'))
    const blocks = useBlocksStore()

    await blocks.load()

    expect(socialApi.blocks).toHaveBeenCalledWith(1)
    expect(blocks.items.map((reader) => reader.handle)).toEqual(['a_reader', 'b_reader'])
    expect(blocks.hasMore).toBe(true)
    expect(blocks.loaded).toBe(true)
  })

  it('appends the next page, without a reader twice', async () => {
    vi.mocked(socialApi.blocks).mockResolvedValueOnce(page(['a_reader', 'b_reader'], '/next')).mockResolvedValueOnce(page(['b_reader', 'c_reader']))
    const blocks = useBlocksStore()
    await blocks.load()

    await blocks.loadMore()

    expect(socialApi.blocks).toHaveBeenLastCalledWith(2)
    expect(blocks.items.map((reader) => reader.handle)).toEqual(['a_reader', 'b_reader', 'c_reader'])
    expect(blocks.hasMore).toBe(false)
  })

  it('keeps what is loaded when "load more" fails', async () => {
    vi.mocked(socialApi.blocks).mockResolvedValueOnce(page(['a_reader'], '/next')).mockRejectedValueOnce(new Error('offline'))
    const blocks = useBlocksStore()
    await blocks.load()

    await expect(blocks.loadMore()).rejects.toThrow('offline')

    expect(blocks.items).toHaveLength(1)
    expect(blocks.loadingMore).toBe(false)
  })

  it('stays unloaded and throws when loading fails', async () => {
    vi.mocked(socialApi.blocks).mockRejectedValue(new ApiError(500, null))
    const blocks = useBlocksStore()

    await expect(blocks.load()).rejects.toBeInstanceOf(ApiError)

    expect(blocks.loaded).toBe(false)
  })

  it('removes a reader from the list once they are unblocked', async () => {
    vi.mocked(socialApi.blocks).mockResolvedValue(page(['a_reader', 'b_reader']))
    vi.mocked(socialApi.unblock).mockResolvedValue(null)
    const blocks = useBlocksStore()
    await blocks.load()

    await blocks.unblock('a_reader')

    expect(socialApi.unblock).toHaveBeenCalledWith('a_reader')
    expect(blocks.items.map((reader) => reader.handle)).toEqual(['b_reader'])
  })

  it('keeps the reader in the list when the server refuses to unblock', async () => {
    vi.mocked(socialApi.blocks).mockResolvedValue(page(['a_reader']))
    vi.mocked(socialApi.unblock).mockRejectedValue(new ApiError(500, null))
    const blocks = useBlocksStore()
    await blocks.load()

    await expect(blocks.unblock('a_reader')).rejects.toBeInstanceOf(ApiError)

    expect(blocks.items).toHaveLength(1)
  })

  it('forgets everything on reset', async () => {
    vi.mocked(socialApi.blocks).mockResolvedValue(page(['a_reader'], '/next'))
    const blocks = useBlocksStore()
    await blocks.load()

    blocks.reset()

    expect(blocks.items).toEqual([])
    expect(blocks.hasMore).toBe(false)
    expect(blocks.loaded).toBe(false)
  })
})
