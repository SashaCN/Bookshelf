import { beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { ApiError } from '@/api/http'
import { authApi } from '@/api/auth'
import { catalogApi, libraryApi } from '@/api/library'
import { quotesApi } from '@/api/quotes'
import { readerApi } from '@/api/reader'
import { socialApi } from '@/api/social'
import { statsApi } from '@/api/stats'
import { installMockApi, resetDemo } from './mockApi'

// The demo runs the real API clients against the fake backend, so these tests go through the clients too.
describe('demo mock API', () => {
  beforeAll(installMockApi)
  beforeEach(resetDemo)

  it('signs the visitor in and out', async () => {
    expect((await authApi.me()).email).toBe('demo@bookshelf.test')

    await authApi.logout()
    await expect(authApi.me()).rejects.toMatchObject({ status: 401 })

    await authApi.login({ email: 'anyone@example.com', password: 'whatever' })
    expect((await authApi.me()).name).toBe('Демо-читач')
  })

  it('moves the bookmark, logs it and finishes the book at the last page', async () => {
    const before = (await libraryApi.get(2)).current_page
    const result = await libraryApi.progress(2, before + 10)

    expect(result.meta.pages).toBe(10)
    expect(result.data.current_page).toBe(before + 10)
    expect((await libraryApi.logs(2)).data[0].to_page).toBe(before + 10)

    const finished = await libraryApi.progress(2, 160)
    expect(finished.meta.reached_end).toBe(true)
    expect((await libraryApi.update(2, { status: 'finished' })).status).toBe('finished')
    await expect(libraryApi.progress(2, 100)).rejects.toBeInstanceOf(ApiError)
  })

  it('applies the status transition rules', async () => {
    await expect(libraryApi.update(3, { status: 'abandoned' })).rejects.toMatchObject({ status: 422 })
    expect((await libraryApi.update(6, { status: 'reading' })).started_at).not.toBeNull()
  })

  it('adds books from the catalog once and from scratch', async () => {
    const found = await catalogApi.search('кобзар')
    expect(found).toHaveLength(1)

    const added = await libraryApi.create({ openlibrary_work_key: found[0].work_key })
    expect(added.book.title).toBe('Кобзар')
    await expect(libraryApi.create({ openlibrary_work_key: found[0].work_key })).rejects.toMatchObject({ status: 409 })

    const manual = await libraryApi.create({ title: 'Своя книга', total_pages: 100 })
    expect(manual.status).toBe('want')
  })

  it('filters and creates quotes, and picks one for the day', async () => {
    expect((await quotesApi.list({ favorite: true })).data.every((q) => q.is_favorite)).toBe(true)
    expect((await quotesApi.list({ book: 3 })).data).toHaveLength(2)

    const created = await quotesApi.create(1, { content: 'Нова цитата', page: 10 })
    expect((await quotesApi.get(created.id)).book.title).toBe('Сто років самотності')
    await expect(quotesApi.create(1, { content: 'Занадто далеко', page: 9999 })).rejects.toMatchObject({ status: 422 })

    expect(await quotesApi.daily()).not.toBeNull()
  })

  it('computes statistics from the reading history', async () => {
    const summary = await statsApi.summary()
    expect(summary.streak.current).toBeGreaterThan(0)
    expect(summary.forecasts.length).toBeGreaterThan(0)

    const daily = await statsApi.daily(14)
    expect(daily.data).toHaveLength(14)
    expect(daily.meta.total_pages).toBe(daily.data.reduce((sum, d) => sum + d.pages, 0))
  })

  it('sets and removes the yearly goal', async () => {
    const year = new Date().getFullYear()
    expect((await statsApi.goal(year)).target_books).toBe(12)
    expect((await statsApi.setGoal(year, 20)).target_books).toBe(20)

    await statsApi.removeGoal(year)
    expect((await statsApi.goal(year)).target_books).toBeNull()
    await expect(statsApi.setGoal(year + 5, 3)).rejects.toMatchObject({ status: 422 })
  })

  it('edits the public profile and demands a username for it', async () => {
    expect(await authApi.me()).toMatchObject({ username: 'demo_reader', is_public: true })

    await expect(authApi.updateMe({ username: null, is_public: true })).rejects.toMatchObject({ status: 422 })
    const updated = await authApi.updateMe({ username: 'new_name', is_public: false })
    expect(updated).toMatchObject({ username: 'new_name', is_public: false })
  })

  it('stores quote visibility', async () => {
    const created = await quotesApi.create(1, { content: 'Для всіх', visibility: 'public' })
    expect(created.visibility).toBe('public')
    expect((await quotesApi.create(1, { content: 'Для себе' })).visibility).toBe('private')
    expect((await quotesApi.update(created.id, { content: 'Для всіх', visibility: 'private' })).visibility).toBe('private')
  })

  it('searches fake readers by name or username', async () => {
    expect((await socialApi.search('олен')).map((p) => p.username)).toEqual(['olena_reads'])
    expect(await socialApi.search('OLENA')).toEqual([{ username: 'olena_reads', name: 'Олена Коваль', is_following: true }])
    expect((await socialApi.search('zzz')).length).toBe(0)
    await expect(socialApi.search('a')).rejects.toMatchObject({ status: 422 })
  })

  it('shows profiles, follows and the feed of fake readers', async () => {
    expect(await socialApi.profile('olena_reads')).toMatchObject({ is_following: true, is_me: false, followers_count: 3 })
    expect((await socialApi.profile('demo_reader')).is_me).toBe(true)
    await expect(socialApi.profile('nobody')).rejects.toMatchObject({ status: 404 })

    const quotes = await socialApi.quotes('taras_books')
    expect(quotes.data).toHaveLength(2)
    expect(quotes.links.next).toBeNull()
    expect((await socialApi.quotes('demo_reader')).data.every((q) => q.visibility === 'public')).toBe(true)

    const feed = await socialApi.feed()
    expect(feed.data.every((q) => q.author.username === 'olena_reads')).toBe(true)

    await socialApi.follow('taras_books')
    expect((await socialApi.following('demo_reader')).data.map((p) => p.username)).toEqual(['olena_reads', 'taras_books'])
    expect((await socialApi.feed()).data.some((q) => q.author.username === 'taras_books')).toBe(true)

    await socialApi.unfollow('olena_reads')
    expect((await socialApi.profile('olena_reads')).is_following).toBe(false)
    expect((await socialApi.followers('demo_reader')).data.map((p) => p.username)).toEqual(['taras_books'])
    await expect(socialApi.follow('demo_reader')).rejects.toMatchObject({ status: 422 })
  })

  it('serves the sample document, saves the position and refuses uploads', async () => {
    const [doc] = await readerApi.list()
    expect(doc.chapters_count).toBe(3)

    const chapter = await readerApi.chapter(doc.id, 1)
    expect(chapter).toMatchObject({ index: 1, count: 3 })
    expect(chapter.html).toContain('<p>')
    await expect(readerApi.chapter(doc.id, 3)).rejects.toMatchObject({ status: 404 })

    expect((await readerApi.savePosition(doc.id, { chapter: 1, fraction: 0.5 })).progress).toBe(50)
    expect((await readerApi.get(doc.id)).position).toEqual({ chapter: 1, fraction: 0.5 })

    await expect(readerApi.upload(new File(['x'], 'a.epub'))).rejects.toMatchObject({ status: 422 })

    await readerApi.remove(doc.id)
    expect(await readerApi.list()).toHaveLength(0)
    await expect(readerApi.get(doc.id)).rejects.toMatchObject({ status: 404 })
  })
})
