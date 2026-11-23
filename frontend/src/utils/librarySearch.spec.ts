import { describe, expect, it } from 'vitest'
import { makeBook, makeUserBook } from '@/test/fixtures'
import { matches, normalize, searchKey, sortBooks } from './librarySearch'

const ub = (id: number, title: string, extra: Partial<Parameters<typeof makeUserBook>[0]> = {}, authors: string[] = []) =>
  makeUserBook({ id, book: makeBook({ id, title, authors }), ...extra })

describe('normalize', () => {
  it('unifies apostrophes, case, accents and spaces', () => {
    expect(normalize("  П’ЯТЬ  ʼ ")).toBe("п'ять '")
    expect(normalize('Café  Zoë')).toBe('cafe zoe')
    expect(normalize('Їжак')).toBe('їжак'.normalize('NFKD').replace(/\p{M}/gu, ''))
  })
})

describe('matches', () => {
  const key = searchKey(ub(1, "Кобзар", {}, ['Тарас Шевченко', "Олесь Гончар"]))

  it('matches everything for an empty query', () => {
    expect(matches(key, '   ')).toBe(true)
  })
  it('matches word prefixes and substrings, case-insensitively', () => {
    expect(matches(key, 'шевч')).toBe(true)
    expect(matches(key, 'ЗАР')).toBe(true)
    expect(matches(key, 'тарас шев')).toBe(true)
    expect(matches(key, 'шевченко кобзар')).toBe(true)
    expect(matches(key, 'франко')).toBe(false)
  })
  it('ignores apostrophe variants', () => {
    const k = searchKey(ub(2, "Пам’ять", {}, []))
    expect(matches(k, "пам'ять")).toBe(true)
    expect(matches(k, 'памʼять')).toBe(true)
    expect(matches(k, 'пямять')).toBe(false)
    expect(matches(k, 'память')).toBe(true)
  })
})

describe('sortBooks', () => {
  const a = ub(1, 'Яблуко', { rating: 3, finished_at: '2024-01-01T00:00:00Z' }, ['Бондар'])
  const b = ub(2, 'Абетка', { rating: null, finished_at: null }, [])
  const c = ub(3, 'Їжак', { rating: 5, finished_at: '2025-01-01T00:00:00Z' }, ['Андрух'])
  const list = [a, b, c]

  it('keeps the server order by default', () => {
    expect(sortBooks(list, 'recent')).toBe(list)
  })
  it('sorts titles with the Ukrainian collation', () => {
    expect(sortBooks(list, 'title').map((x) => x.id)).toEqual([2, 3, 1])
  })
  it('sorts by author with authorless last', () => {
    expect(sortBooks(list, 'author').map((x) => x.id)).toEqual([3, 1, 2])
  })
  it('puts rated books first, best first', () => {
    expect(sortBooks(list, 'rating').map((x) => x.id)).toEqual([3, 1, 2])
  })
  it('sorts by finish date, newest first, unfinished last', () => {
    expect(sortBooks(list, 'finished').map((x) => x.id)).toEqual([3, 1, 2])
  })
  it('does not mutate the input', () => {
    sortBooks(list, 'title')
    expect(list.map((x) => x.id)).toEqual([1, 2, 3])
  })
})
